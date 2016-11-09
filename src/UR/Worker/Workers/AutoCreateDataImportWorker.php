<?php

namespace UR\Worker\Workers;

use StdClass;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Liuggio\ExcelBundle\Factory;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryImportHistoryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\Alert;
use UR\Entity\Core\DataSourceEntryImportHistory;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Repository\Core\ConnectedDataSourceRepository;
use UR\Repository\Core\DataSourceRepository;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;
use UR\Service\Parser\ImportUtils;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;

class AutoCreateDataImportWorker
{
    /** @var EntityManagerInterface $em */
    private $em;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var DataSourceEntryImportHistoryManagerInterface
     */
    private $dataSourceEntryImportHistoryManager;

    /**
     * @var AlertManagerInterface
     */
    private $alertManager;

    /**
     * @var Factory
     */
    private $phpExcel;

    function __construct(DataSetManagerInterface $dataSetManager, ImportHistoryManagerInterface $importHistoryManager, AlertManagerInterface $alertManager, DataSourceEntryImportHistoryManagerInterface $dataSourceEntryImportHistoryManager, EntityManagerInterface $em, Factory $phpExcel)
    {
        $this->dataSetManager = $dataSetManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->alertManager = $alertManager;
        $this->dataSourceEntryImportHistoryManager = $dataSourceEntryImportHistoryManager;
        $this->em = $em;
        $this->phpExcel = $phpExcel;
    }

    //function autoCreateDataImport($dataSetId, $filePath)
    function autoCreateDataImport(StdClass $params)
    {
        $dataSetId = $params->dataSetId;
        $filePath = $params->filePath;

        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn);

        // get all info of job..
        /**@var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new InvalidArgumentException('not found Dataset with this ID');
        }
        $importUtils = new ImportUtils();
        //create or update empty dataSet table
        if (!$dataSetLocator->getDataSetImportTable($dataSetId)) {
            $importUtils->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $conn);
        }

        $connectedDataSources = $dataSet->getConnectedDataSources();

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($connectedDataSources as $connectedDataSource) {
            // create importHistory: createdTime
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setConnectedDataSource($connectedDataSource);
            //$importHistoryEntity->setDescription(); // TODO: set later
            $this->importHistoryManager->save($importHistoryEntity);

            //get all dataSource entries
            $dse = $connectedDataSource->getDataSource()->getDataSourceEntries();

            $parser = new Parser();

            /**@var DataSourceEntryInterface $item */
            foreach ($dse as $item) {

                // mapping
                $parserConfig = new ParserConfig();
                if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'csv') === 0) {
                    /**@var Csv $file */
                    $file = (new Csv($filePath . $item->getPath()))->setDelimiter(',');
                } else if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'excel') === 0) {
                    /**@var Excel $file */
                    $file = new \UR\Service\DataSource\Excel($filePath . $item->getPath(), $this->phpExcel);
                } else {
                    $file = new Json($item->getPath());
                }

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
                    $this->createDataSourceEntryHistory($item, $importHistoryEntity, "failure", "error when mapping require fields");
                    continue;
                }

                //filter
                $importUtils->filterDataSetTable($connectedDataSource, $parserConfig);

                //transform
                $importUtils->transformDataSetTable($connectedDataSource, $parserConfig);

                // import
                $collectionParser = $parser->parse($file, $parserConfig);

                if (is_array($collectionParser)) {
                    if (strcmp($collectionParser["error"], "filter") === 0) {
                        $desc = "error when Filter file at row " . $collectionParser["row"] . " column " . $collectionParser["column"];
                    }

                    if (strcmp($collectionParser["error"], "transform") === 0) {
                        $desc = "error when Transform file at row " . $collectionParser["row"] . " column " . $collectionParser["column"];
                    }

                    $this->createDataSourceEntryHistory($item, $importHistoryEntity, "failure", $desc);

                    $alertSetting = $connectedDataSource->getAlertSetting();
                    if (strcmp($collectionParser["error"], "filter") === 0 || strcmp($collectionParser["error"], "transform") === 0) {
                        $title = "Import data failure";
                        $type = "error";
                        if (in_array(ConnectedDataSourceRepository::IMPORT_FAILURE, $alertSetting)) {
                            $this->createImportedDataAlert($item, $title, $type, $desc);
                        }
                    }
                    continue;
                }

                $ds1 = $dataSetLocator->getDataSetImportTable($dataSetId);

                $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource->getDataSource()->getId());

                $alertSetting = $connectedDataSource->getAlertSetting();
                if (in_array(ConnectedDataSourceRepository::DATA_ADDED, $alertSetting)) {
                    $title = "Import data successfully";
                    $type = "info";
                    $arrayPath = explode('/', $item->getPath());
                    $fileNameTemp = $arrayPath[count($arrayPath) - 1];
                    $lastDash = strrpos($fileNameTemp, "_");
                    $lastFileNamePath = substr($fileNameTemp, $lastDash);
                    $arrayLastPath = explode('.', $lastFileNamePath);
                    $extension = $arrayLastPath[1];
                    $firstFileNamePath = substr($fileNameTemp, 0, strlen($fileNameTemp) - strlen($lastFileNamePath));
                    $fileName = $firstFileNamePath . "." . $extension;
                    $desc = "File ". $fileName . " of " . $connectedDataSource->getDataSource()->getName() . " and " . $connectedDataSource->getDataSet()->getName() . " is imported";
                    $this->createImportedDataAlert($item, $title, $type, $desc);
                }
            }
        }
    }

    function createImportedDataAlert(DataSourceEntryInterface $item, $title, $type, $message)
    {
        $importedDataAlert = new Alert();
        $importedDataAlert->setDataSourceEntry($item);
        $importedDataAlert->setTitle($title);
        $importedDataAlert->setType($type);
        $importedDataAlert->setMessage($message);
        $this->alertManager->save($importedDataAlert);
    }

    function createDataSourceEntryHistory(DataSourceEntryInterface $item, $importHistoryEntity, $status, $desc)
    {
        $dseImportHistoryEntity = new DataSourceEntryImportHistory();
        $dseImportHistoryEntity->setDataSourceEntry($item);
        $dseImportHistoryEntity->setImportHistory($importHistoryEntity);
        $dseImportHistoryEntity->setStatus($status);
        $dseImportHistoryEntity->setDescription($desc);
        $this->dataSourceEntryImportHistoryManager->save($dseImportHistoryEntity);
    }
}