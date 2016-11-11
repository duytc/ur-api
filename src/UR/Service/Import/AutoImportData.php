<?php

namespace UR\Service\Import;


use Liuggio\ExcelBundle\Factory;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSourceEntryImportHistoryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\DataSourceEntryImportHistory;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Type;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;
use UR\Service\Parser\History\HistoryType;
use UR\Service\Parser\ImportUtils;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;
use UR\Worker\Manager;

class AutoImportData implements AutoImportDataInterface
{
    /** @var Manager */
    private $workerManager;

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

    /**
     * @var Factory
     */
    private $filePath;

    function __construct(Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, AlertManagerInterface $alertManager, DataSourceEntryImportHistoryManagerInterface $dataSourceEntryImportHistoryManager, Factory $phpExcel, $filePath)
    {
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->alertManager = $alertManager;
        $this->dataSourceEntryImportHistoryManager = $dataSourceEntryImportHistoryManager;
        $this->phpExcel = $phpExcel;
        $this->filePath = $filePath;
    }

    public function autoCreateDataImport(ConnectedDataSourceInterface $connectedDataSource, Importer $dataSetImporter, Locator $dataSetLocator)
    {
        $importUtils = new ImportUtils();
        $importHistoryEntity = new ImportHistory();
        $importHistoryEntity->setConnectedDataSource($connectedDataSource);
        //$importHistoryEntity->setDescription(); // TODO: set later
        $this->importHistoryManager->save($importHistoryEntity);

        //get all dataSource entries
        $dse = $connectedDataSource->getDataSource()->getDataSourceEntries();
        $dataSetId = $connectedDataSource->getDataSet()->getId();

        $parser = new Parser();

        /**@var DataSourceEntryInterface $item */
        foreach ($dse as $item) {

            // mapping
            $parserConfig = new ParserConfig();
            $errors=array();

            $errors[HistoryType::ERROR_CODE] = 0;
            $errors[HistoryType::DATA_SOURCE_ENTRY_ENTITY] = $item->getId();
            $errors[HistoryType::IMPORT_HISTORY_ENTITY] = $importHistoryEntity->getId();

            if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'csv') === 0) {
                /**@var Csv $file */
                $file = (new Csv($this->filePath . $item->getPath()))->setDelimiter(',');
            } else if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'excel') === 0) {
                /**@var Excel $file */
                $file = new \UR\Service\DataSource\Excel($this->filePath . $item->getPath(), $this->phpExcel);
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
                $errors[HistoryType::ERROR_CODE] = 1;
                $this->workerManager->createImportHistoryWhenConnectedDataSourceChange($errors);
                continue;
            }

            //filter
            $importUtils->filterDataSetTable($connectedDataSource, $parserConfig);

            //transform
            $importUtils->transformDataSetTable($connectedDataSource, $parserConfig);

            // import
            $collectionParser = $parser->parse($file, $parserConfig);

            if (is_array($collectionParser)) {
                $errors=$collectionParser;
                $errors[HistoryType::DATA_SOURCE_ENTRY_ENTITY] = $item->getId();
                $errors[HistoryType::IMPORT_HISTORY_ENTITY] = $importHistoryEntity->getId();
                $this->workerManager->createImportHistoryWhenConnectedDataSourceChange($errors);
                continue;
            }

            $this->workerManager->createImportHistoryWhenConnectedDataSourceChange($errors);

            $ds1 = $dataSetLocator->getDataSetImportTable($dataSetId);
            $dataSetImporter->importCollection($collectionParser, $ds1, $importHistoryEntity->getId(), $connectedDataSource->getDataSource()->getId());
        }
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