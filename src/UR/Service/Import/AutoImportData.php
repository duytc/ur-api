<?php

namespace UR\Service\Import;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Liuggio\ExcelBundle\Factory;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
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

    public function autoCreateDataImport(DataSetInterface $dataSet, DataSourceEntryInterface $dataSourceEntry)
    {
        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn);

        $importUtils = new ImportUtils();

        //create or update empty dataSet table
        if (!$dataSetLocator->getDataSetImportTable($dataSet->getId())) {
            $importUtils->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $conn);
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

        $connectedDataSources = $dataSourceEntry->getDataSource()->getConnectedDataSources();
        foreach ($connectedDataSources as $connectedDataSource) {
            // to do alert
            $code = ProcessAlert::NEW_DATA_IS_ADD_TO_CONNECTED_DATA_SOURCE;
            $publisherId = $dataSourceEntry->getDataSource()->getPublisherId();
            $params = array (
                ProcessAlert::DATA_SET_NAME => $dataSet->getName(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSourceEntry->getDataSource()->getName(),
                ProcessAlert::DATA_SOURCE_ENTRY_PATH => $dataSourceEntry->getPath()
            );

            $importUtils->mappingFile($connectedDataSource, $parserConfig, $file);

            if (count($parserConfig->getAllColumnMappings()) === 0) {
                continue;
            }

            $validRequires = true;
            foreach ($connectedDataSource->getRequires() as $require) {
                if (!array_key_exists($require, $parserConfig->getAllColumnMappings())) {
                    $validRequires = false;
                    break;
                }
            }

            if (!$validRequires) {
                // to do alert
                $code = ProcessAlert::DATA_IMPORT_REQUIRED_FAIL;
                $this->workerManager->processAlert($code, $publisherId, $params);
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
                $code = $collectionParser['error'];
                $params[ProcessAlert::ROW] = $collectionParser[ProcessAlert::ROW];
                $params[ProcessAlert::COLUMN] = $collectionParser[ProcessAlert::COLUMN];
                $this->workerManager->processAlert($code, $publisherId, $params);
                continue;
            }

            $ds1 = $dataSetLocator->getDataSetImportTable($dataSet->getId());
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
            $importHistoryEntity->setDataSet($dataSet);
            $this->importHistoryManager->save($importHistoryEntity);
            $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource->getDataSource()->getId());
            // to do alert
            $this->workerManager->processAlert($code, $publisherId, $params );
        }
    }
}