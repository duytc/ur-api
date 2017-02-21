<?php

namespace UR\Service\Import;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Liuggio\ExcelBundle\Factory;
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
     * @var ImportDataLogger
     */
    private $logger;

    private $batchSize;

    private $chunkSize;

    protected $parser;

    function __construct(EntityManagerInterface $em, Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, Factory $phpExcel, $uploadFileDir, ImportDataLogger $logger, $batchSize, $chunkSize, Parser $parser)
    {
        $this->em = $em;
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->phpExcel = $phpExcel;
        $this->uploadFileDir = $uploadFileDir;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
        $this->chunkSize = $chunkSize;
        $this->parser = $parser;
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
                $this->logger->doExceptionLogging('not found Dataset with this ID');
                throw new InvalidArgumentException('not found Dataset with this ID');
            }

            //create or update empty dataSet table
            if (!$dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId())) {
                $importUtils->createEmptyDataSetTable($connectedDataSource->getDataSet(), $dataSetLocator, $dataSetSynchronizer, $conn, $this->logger);
            }

            // mapping
            $parserConfig = new ParserConfig();
            $publisherId = $dataSourceEntry->getDataSource()->getPublisherId();
            $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntry($dataSourceEntry, $connectedDataSource->getDataSet());
            $importHistoryEntity = new ImportHistory();

            $params = array(
                ProcessAlert::DATA_SET_ID => $connectedDataSource->getDataSet()->getId(),
                ProcessAlert::DATA_SET_NAME => $connectedDataSource->getDataSet()->getName(),
                ProcessAlert::DATA_SOURCE_ID => $dataSourceEntry->getDataSource()->getId(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSourceEntry->getDataSource()->getName(),
                ProcessAlert::ENTRY_ID => $dataSourceEntry->getId(),
                ProcessAlert::FILE_NAME => $dataSourceEntry->getFileName()
            );

            try {
                $filePath = $this->uploadFileDir . $dataSourceEntry->getPath();
                if (!file_exists($filePath)) {
                    throw  new ImportDataException(ProcessAlert::ALERT_CODE_FILE_NOT_FOUND, null, null);
                }

                if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'csv') === 0) {
                    /**@var Csv $file */
                    $file = new Csv($filePath);
                } else if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'excel') === 0) {
                    /**@var Excel $file */
                    $file = new Excel($filePath, $this->phpExcel, $this->chunkSize);
                } else {
                    $file = new Json($filePath);
                    $this->logger->doLoggingForJson($connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId());
                }

                $code = ProcessAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY;
                $columns = $file->getColumns();
                $dataRow = $file->getDataRow();
                if (!is_array($columns) || count($columns) < 1) {
                    throw  new ImportDataException(ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND, null, null);
                }

                if ($dataRow < 1) {
                    throw  new ImportDataException(ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND, null, null);
                }

                $importUtils->createMapFieldsConfigForConnectedDataSource($connectedDataSource, $parserConfig, $columns);
                if (count($parserConfig->getAllColumnMappings()) === 0) {
                    throw  new ImportDataException(ProcessAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL, null, null);
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
                        throw  new ImportDataException(ProcessAlert::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL, null, $columnRequire);
                    }
                    continue;
                }

                //filter config
                $importUtils->createFilterConfigForConnectedDataSource($connectedDataSource, $parserConfig);

                //transform
                $parserConfig->setUserReorderTransformsAllowed($connectedDataSource->isUserReorderTransformsAllowed());
                $importUtils->createTransformConfigForConnectedDataSource($connectedDataSource, $parserConfig, $dataSourceEntry);

                // parser
                $collectionParser = $this->parser->parse($file, $parserConfig, $connectedDataSource);

                $ds1 = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
                // import

                $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
                $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
                $this->importHistoryManager->save($importHistoryEntity);
                $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource);
                if (in_array(ConnectedDataSourceRepository::DATA_ADDED, $connectedDataSource->getAlertSetting())) {
                    $this->logger->doImportLogging($code, $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), null, null);
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    $this->importHistoryManager->deletePreviousImports($importHistories);
                }

            } catch (ImportDataException $e) {
                if ($e->getAlertCode() === null) {
                    $params[ProcessAlert::MESSAGE] = "Unexpected Error";
                    $this->workerManager->processAlert(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $publisherId, $params);
                    $message = sprintf("data-set#%s data-source#%s data-source-entry#%s (message: %s)", $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getMessage());
                    $this->logger->doExceptionLogging($message);
                    if ($importHistoryEntity->getId() !== null) {
                        $this->importHistoryManager->delete($importHistoryEntity);
                    }
                } else {
                    $params[ProcessAlert::ROW] = $e->getRow();
                    $params[ProcessAlert::COLUMN] = $e->getColumn();
                    $this->logger->doImportLogging($e->getAlertCode(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
                    $this->workerManager->processAlert($e->getAlertCode(), $publisherId, $params);
                }
            }
        }
    }
}