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

    function __construct(EntityManagerInterface $em, Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, Factory $phpExcel, $uploadFileDir, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->phpExcel = $phpExcel;
        $this->uploadFileDir = $uploadFileDir;
        $this->logger = $logger;
    }

    public function autoCreateDataImport($connectedDataSources, DataSourceEntryInterface $dataSourceEntry)
    {
        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn);

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
            $params = array(
                ProcessAlert::DATA_SET_NAME => $connectedDataSource->getDataSet()->getName(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSourceEntry->getDataSource()->getName(),
                ProcessAlert::FILE_NAME => $dataSourceEntry->getFileName()
            );

            try {
                if (!file_exists($dataSourceEntry->getPath())) {
                    $params[ProcessAlert::MESSAGE] = ' file dose not exist ';
                    $this->workerManager->processAlert(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $publisherId, $params);
                    continue;
                }

                if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'csv') === 0) {
                    /**@var Csv $file */
                    $file = new Csv($this->uploadFileDir . $dataSourceEntry->getPath());
                } else if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'excel') === 0) {
                    /**@var Excel $file */
                    $file = new Excel($this->uploadFileDir . $dataSourceEntry->getPath(), $this->phpExcel);
                } else {
                    $file = new Json($dataSourceEntry->getPath());
                }

                $code = ProcessAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY;
                $columns = $file->getColumns();
                $dataRow = $file->getDataRow();
                if (!is_array($columns) || count($columns) < 1) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND;
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    continue;
                }

                if ($dataRow < 1) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND;
                    $this->workerManager->processAlert($code, $publisherId, $params);
                    continue;
                }

                $importUtils->mappingFile($connectedDataSource, $parserConfig, $columns);
                if (count($parserConfig->getAllColumnMappings()) === 0) {
                    $code = ProcessAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL;
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
                        $this->workerManager->processAlert($code, $publisherId, $params);
                    }
                    continue;
                }

                //filter
                $importUtils->filterDataSetTable($connectedDataSource, $parserConfig);

                //transform
                $importUtils->transformDataSetTable($connectedDataSource, $parserConfig);

                // import
                $collectionParser = $parser->parse($file, $parserConfig, $connectedDataSource);

                if (is_array($collectionParser)) {
                    // to do alert
                    if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $connectedDataSource->getAlertSetting())) {
                        $code = $collectionParser[ProcessAlert::ERROR];
                        if (array_key_exists(ProcessAlert::MESSAGE, $collectionParser)) {
                            $params[ProcessAlert::MESSAGE] = $collectionParser[ProcessAlert::MESSAGE];
                        } else {
                            $params[ProcessAlert::ROW] = $collectionParser[ProcessAlert::ROW];
                            $params[ProcessAlert::COLUMN] = $collectionParser[ProcessAlert::COLUMN];
                        }

                        $this->workerManager->processAlert($code, $publisherId, $params);
                    }
                    continue;
                }

                $ds1 = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
                $this->importHistoryManager->reImportDataSourceEntry($dataSourceEntry, $connectedDataSource->getDataSet());
                $importHistoryEntity = new ImportHistory();
                $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
                $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
                $this->importHistoryManager->save($importHistoryEntity);
                // to do alert
                $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource);
                if (in_array(ConnectedDataSourceRepository::DATA_ADDED, $connectedDataSource->getAlertSetting())) {
                    $this->workerManager->processAlert($code, $publisherId, $params);
                }

            } catch (\Exception $e) {
                $params[ProcessAlert::MESSAGE] = "Unexpected Error";
                $this->workerManager->processAlert(ProcessAlert::ALERT_CODE_UN_EXPECTED_ERROR, $publisherId, $params);
                $this->logger->error($e->getMessage());
            }
        }
    }
}