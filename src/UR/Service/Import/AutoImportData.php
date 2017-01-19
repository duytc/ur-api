<?php

namespace UR\Service\Import;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Liuggio\ExcelBundle\Factory;
use Psr\Log\LoggerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Repository\Core\ConnectedDataSourceRepository;
use UR\Service\Alert\ProcessAlert;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;
use UR\Service\Parser\ImportUtils;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;
use UR\Worker\Manager;

class AutoImportData implements AutoImportDataInterface
{
    /** @var EntityManagerInterface $em */
    private $em;

    /** @var Manager */
    private $workerManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var Factory
     */
    private $phpExcel;

    /**
     * @var string
     */
    private $uploadFileDir;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $batchSize;

    private $chunkSize;

    function __construct(EntityManagerInterface $em, Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, Factory $phpExcel, $uploadFileDir, LoggerInterface $logger, $batchSize, $chunkSize)
    {
        $this->em = $em;
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->phpExcel = $phpExcel;
        $this->uploadFileDir = $uploadFileDir;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
        $this->chunkSize = $chunkSize;
    }

    public function autoCreateDataImport($connectedDataSources, DataSourceEntryInterface $dataSourceEntry)
    {
        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn, $this->batchSize);

        $importUtils = new ImportUtils();

        /**
         * @var ConnectedDataSourceInterface $connectedDataSource
         */
        foreach ($connectedDataSources as $connectedDataSource) {
            if ($connectedDataSource->getDataSet() === null) {
                $this->logger->error('not found Dataset with this ID');
                throw new InvalidArgumentException('not found Dataset with this ID');
            }

            //create or update empty dataSet table
            if (!$dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId())) {
                $importUtils->createEmptyDataSetTable($connectedDataSource->getDataSet(), $dataSetLocator, $dataSetSynchronizer, $conn, $this->logger);
            }

            $parser = new Parser();

            // mapping
            $parserConfig = new ParserConfig();
            $publisherId = $dataSourceEntry->getDataSource()->getPublisherId();
            $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntry($dataSourceEntry, $connectedDataSource->getDataSet());
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
            $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
            $this->importHistoryManager->save($importHistoryEntity);
            $params = array(
                ProcessAlert::IMPORT_ID => $importHistoryEntity->getId(),
                ProcessAlert::DATA_SET_ID => $connectedDataSource->getDataSet()->getId(),
                ProcessAlert::DATA_SET_NAME => $connectedDataSource->getDataSet()->getName(),
                ProcessAlert::DATA_SOURCE_ID => $dataSourceEntry->getDataSource()->getId(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSourceEntry->getDataSource()->getName(),
                ProcessAlert::ENTRY_ID => $dataSourceEntry->getId(),
                ProcessAlert::FILE_NAME => $dataSourceEntry->getFileName()
            );

            try {
                if (!file_exists($this->uploadFileDir . $dataSourceEntry->getPath())) {
                    $params[ProcessAlert::MESSAGE] = ' file does not exist ';
                    $this->doImportLogging(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $publisherId, $params);
                    continue;
                }

                if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'csv') === 0) {
                    /**@var Csv $file */
                    $file = new Csv($this->uploadFileDir . $dataSourceEntry->getPath());
                } else if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'excel') === 0) {
                    /**@var Excel $file */
                    $file = new Excel($this->uploadFileDir . $dataSourceEntry->getPath(), $this->phpExcel, $this->chunkSize);
                } else {
                    $file = new Json($dataSourceEntry->getPath());
                }

                $code = ProcessAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY;
                $columns = $file->getColumns();
                $dataRow = $file->getDataRow();
                if (!is_array($columns) || count($columns) < 1) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND;
                    $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    continue;
                }

                if ($dataRow < 1) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND;
                    $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    continue;
                }

                $importUtils->createMapFieldsConfigForConnectedDataSource($connectedDataSource, $parserConfig, $columns);
                if (count($parserConfig->getAllColumnMappings()) === 0) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL;
                    $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    continue;
                }

                $validRequires = true;
                $columnRequire = '';
                foreach ($connectedDataSource->getRequires() as $require) {
                    if (!array_key_exists($require, $parserConfig->getAllColumnMappings())) {
                        $columnRequire = $require;
                        $validRequires = false;
                        break;
                    }
                }

                if (!$validRequires) {
                    // to do alert
                    if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $connectedDataSource->getAlertSetting())) {
                        $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL;
                        $params[ProcessAlert::COLUMN] = $columnRequire;
                        $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, $columnRequire);
                        $this->workerManager->processAlert($code, $publisherId, $params);
                    }
                    continue;
                }

                //filter
                $importUtils->createFilterConfigForConnectedDataSource($connectedDataSource, $parserConfig);

                //transform
                $importUtils->createTransformConfigForConnectedDataSource($connectedDataSource, $parserConfig);

                // import
                $collectionParser = $parser->parse($file, $parserConfig, $connectedDataSource);

                if (is_array($collectionParser)) {
                    // to do alert
                    if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $connectedDataSource->getAlertSetting())) {
                        $code = $collectionParser[ProcessAlert::ERROR];
                        if (array_key_exists(ProcessAlert::MESSAGE, $collectionParser)) {
                            $params[ProcessAlert::MESSAGE] = $collectionParser[ProcessAlert::MESSAGE];
                        }

                        $params[ProcessAlert::ROW] = array_key_exists(ProcessAlert::ROW, $collectionParser) ? $collectionParser[ProcessAlert::ROW] : null;
                        $params[ProcessAlert::COLUMN] = array_key_exists(ProcessAlert::COLUMN, $collectionParser) ? $collectionParser[ProcessAlert::COLUMN] : null;
                        $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $params[ProcessAlert::ROW], $params[ProcessAlert::COLUMN]);
                        $this->workerManager->processAlert($code, $publisherId, $params);
                    }
                    continue;
                }

                $ds1 = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
                // to do alert
                $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource);
                if (in_array(ConnectedDataSourceRepository::DATA_ADDED, $connectedDataSource->getAlertSetting())) {
                    $this->doImportLogging($code, $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    $this->importHistoryManager->deletePreviousImports($importHistories);
                }

            } catch (\Exception $e) {
                $params[ProcessAlert::MESSAGE] = "Unexpected Error";
                $this->workerManager->processAlert(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $publisherId, $params);
                $this->logger->error(sprintf("ur-import#%s data-set#%s data-source#%s data-source-entry#%s (message: %s)", $importHistoryEntity->getId(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getMessage()));
            }
        }
    }

    private function doImportLogging($errorCode, $importId, $dataSetId, $dataSourceId, $dataSourceEntryId, $row, $column)
    {
        $message = "";
        switch ($errorCode) {
            case ProcessAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY:
                $message = sprintf("Data imported successfully");
                break;
            case ProcessAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf("failed to import file with id#%s - MAPPING ERROR: no Field is mapped", $dataSourceEntryId);
                break;

            case ProcessAlert::ALERT_CODE_WRONG_TYPE_MAPPING:
                $message = sprintf("Failed to import file with id#%s - MAPPING ERROR: wrong type mapping", $dataSourceEntryId);
                break;

            case ProcessAlert::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf("Failed to import file with id#%s - REQUIRE ERROR: require fields not exist in file", $dataSourceEntryId);
                break;

            case ProcessAlert::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf("Failed to import file with id#%s - FILTER ERROR: Wrong number format at row [%s] - column [%s]", $dataSourceEntryId, $row, $column);
                break;
            case ProcessAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf("Failed to import file with id#%s - TRANSFORM ERROR: Wrong date format at row [%s] - column [%s] ", $dataSourceEntryId, $row, $column);
                break;

            case ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf("Failed to import file with id#%s - no header found error", $dataSourceEntryId);
                break;

            case ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf("Failed to import file with id#%s - can't find data error", $dataSourceEntryId);
                break;

            case ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR:
                $message = sprintf("Failed to import file with id#%s - file dose not exist", $dataSourceEntryId);
                break;
            default:
                break;
        }

        if ($errorCode == ProcessAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY) {
            $this->logger->info(sprintf("ur-import#%s data-set#%s data-source#%s data-source-entry#%s (message: %s)", $importId, $dataSetId, $dataSourceId, $dataSourceEntryId, $message));
        } else {
            $this->logger->error(sprintf("ur-import#%s data-set#%s data-source#%s data-source-entry#%s (message: %s)", $importId, $dataSetId, $dataSourceId, $dataSourceEntryId, $message));
        }
    }
}