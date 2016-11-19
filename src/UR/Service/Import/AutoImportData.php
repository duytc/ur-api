<?php

namespace UR\Service\Import;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Liuggio\ExcelBundle\Factory;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
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
     * @var AlertManagerInterface
     */
    private $alertManager;

    /**
     * @var Factory
     */
    private $phpExcel;

    /**
     * @var string
     */
    private $uploadFileDir;

    function __construct(EntityManagerInterface $em, Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, AlertManagerInterface $alertManager, Factory $phpExcel, $uploadFileDir)
    {
        $this->em = $em;
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->alertManager = $alertManager;
        $this->phpExcel = $phpExcel;
        $this->uploadFileDir = $uploadFileDir;
    }

    public function autoCreateDataImport($connectedDataSources, DataSourceEntryInterface $dataSourceEntry)
    {
        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn);

        $importUtils = new ImportUtils();

        foreach ($connectedDataSources as $connectedDataSource) {
            if ($connectedDataSource->getDataSet() === null) {
                throw new InvalidArgumentException('not found Dataset with this ID');
            }
            //create or update empty dataSet table
            if (!$dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId())) {
                $importUtils->createEmptyDataSetTable($connectedDataSource->getDataSet(), $dataSetLocator, $dataSetSynchronizer, $conn);
            }

            $parser = new Parser();

            // mapping
            $parserConfig = new ParserConfig();

            if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'csv') === 0) {
                /**@var Csv $file */
                $file = (new Csv($this->uploadFileDir . $dataSourceEntry->getPath()))->setDelimiter(',');
            } else if (strcmp($dataSourceEntry->getDataSource()->getFormat(), 'excel') === 0) {
                /**@var Excel $file */
                $file = new \UR\Service\DataSource\Excel($this->uploadFileDir . $dataSourceEntry->getPath(), $this->phpExcel);
            } else {
                $file = new Json($dataSourceEntry->getPath());
            }

            $importUtils->mappingFile($connectedDataSource, $parserConfig, $file);

            $code = ProcessAlert::NEW_DATA_IS_ADD_TO_CONNECTED_DATA_SOURCE;
            $publisherId = $dataSourceEntry->getDataSource()->getPublisherId();

            // prepare alert params: default is success
            $params = array (
                ProcessAlert::DATA_SET_NAME => $connectedDataSource->getDataSet()->getName(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSourceEntry->getDataSource()->getName(),
                ProcessAlert::FILE_NAME => $dataSourceEntry->getFileName()
            );

            if (count($parserConfig->getAllColumnMappings()) === 0) {
                $code = ProcessAlert::DATA_IMPORT_MAPPING_FAIL;
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
                if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $connectedDataSource->getAlertSetting())){
                    $code = ProcessAlert::DATA_IMPORT_REQUIRED_FAIL;
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
            $collectionParser = $parser->parse($file, $parserConfig);

            if (is_array($collectionParser)) {
                // to do alert
                if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $connectedDataSource->getAlertSetting())) {
                    $code = $collectionParser['error'];
                    $params[ProcessAlert::ROW] = $collectionParser[ProcessAlert::ROW];
                    $params[ProcessAlert::COLUMN] = $collectionParser[ProcessAlert::COLUMN];
                    $this->workerManager->processAlert($code, $publisherId, $params);
                }
                continue;
            }

            $ds1 = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
            $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
            $this->importHistoryManager->save($importHistoryEntity);
            // to do alert
            $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource->getDataSource()->getId());
            if (in_array(ConnectedDataSourceRepository::DATA_ADDED, $connectedDataSource->getAlertSetting())) {
                $this->workerManager->processAlert($code, $publisherId, $params);
            }
        }
    }
}