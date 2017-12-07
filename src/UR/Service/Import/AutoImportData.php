<?php

namespace UR\Service\Import;


use Monolog\Logger;
use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;
use UR\DomainManager\MapBuilderConfigManager;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\ArrayUtilTrait;
use UR\Service\DataSet\DataMappingService;
use UR\Service\DataSet\ParsedDataImporter;
use UR\Service\DataSet\UpdateDataSetTotalRowService;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DTO\Collection;
use UR\Service\Parser\DryRunReportFilterInterface;
use UR\Service\Parser\DryRunReportSorterInterface;
use UR\Service\Parser\ParserInterface;
use UR\Service\Parser\ParsingFileService;

class AutoImportData implements AutoImportDataInterface
{
    use ArrayUtilTrait;
    const DATA_REPORTS = 'reports';
    const DATA_COLUMNS = 'columns';
    const DATA_TOTAL = 'total';
    const DATA_AVERAGE = 'average';
    const DATA_TYPES = 'types';
    const DATA_RANGE = 'range';

    /**
     * @var ParsedDataImporter
     */
    private $importer;

    /**
     * @var ParsingFileService
     */
    private $parsingFileService;

    private $logger;

    /** @var DryRunReportSorterInterface */
    private $dryRunReportSorter;

    /** @var DryRunReportFilterInterface */
    private $dryRunReportFilter;

    /** @var MapBuilderConfigManager */
    protected $mapBuilderConfigManager;

    /** @var DataMappingService */
    protected $dataMappingService;

    /** @var CsvWriterInterface  */
    protected $csvWriter;

    /** @var DataSourceFileFactory  */
    private $dataSourceFileFactory;

    /** @var UpdateDataSetTotalRowService  */
    protected $updateDataSetTotalRowService;

    function __construct(ParsingFileService $parsingFileService, ParsedDataImporter $importer, Logger $logger, DryRunReportSorterInterface $dryRunReportSorter, DryRunReportFilterInterface $dryRunReportFilter, MapBuilderConfigManager $mapBuilderConfigManager, DataMappingService $dataMappingService, CsvWriterInterface $csvWriter, DataSourceFileFactory $dataSourceFileFactory, UpdateDataSetTotalRowService $updateDataSetTotalRowService)
    {
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
        $this->logger = $logger;
        $this->dryRunReportSorter = $dryRunReportSorter;
        $this->dryRunReportFilter = $dryRunReportFilter;
        $this->mapBuilderConfigManager = $mapBuilderConfigManager;
        $this->dataMappingService = $dataMappingService;
        $this->csvWriter = $csvWriter;
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->updateDataSetTotalRowService = $updateDataSetTotalRowService;
    }

    /**
     * @inheritdoc
     */
    public function loadingDataFromFileToDatabase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry);
        $this->importToDataBase($connectedDataSource, $dataSourceEntry,$importHistoryEntity, $collection);
    }

    /**
     * @inheritdoc
     */
    public function parseFileOnPreGroups(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity, $chunkFilePath, $outputFilePath)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, null, $parserType = ParserInterface::TYPE_PRE_GROUPS, $chunkFilePath);
        $this->csvWriter->insertCollection($outputFilePath, $collection);
    }

    /**
     * @inheritdoc
     */
    public function parseFileOnGroups(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, $chunkFilePath, $outputFilePath)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, null, $parserType = ParserInterface::TYPE_GROUPS, $chunkFilePath);

        $this->csvWriter->insertCollection($outputFilePath, $collection);
    }

    /**
     * @inheritdoc
     */
    public function parseFileOnPostGroups(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity, $chunkFilePath)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, null, $parserType = ParserInterface::TYPE_POST_GROUPS, $chunkFilePath);
        $this->importToDataBase($connectedDataSource, $dataSourceEntry,$importHistoryEntity, $collection);
    }

    public function parseFileThenInsert(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity, $chunkFilePath)
    {
        /* parsing data */
        try {
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, null, $parserType = ParserInterface::TYPE_DEFAULT, $chunkFilePath);
        } catch (ImportDataException $ex) {
            throw $ex;
        }
        $this->importToDataBase($connectedDataSource, $dataSourceEntry,$importHistoryEntity, $collection);
    }

    /**
     * @inheritdoc
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, DryRunParamsInterface $dryRunParams)
    {
        try {
            /** @var \UR\Service\DataSource\DataSourceInterface $dataSourceFileData */
            $dataSourceFileData = null;

            $chunks = $dataSourceEntry->getChunks();
            $chunkFilePath = $dataSourceEntry->isSeparable() && !empty($chunks) && is_array($chunks)? $this->dataSourceFileFactory->getAbsolutePath(reset($chunks)) : "";

            if (!empty($chunkFilePath) && is_file($chunkFilePath)) {
                $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, $dryRunParams->getLimitRows(), $parserType = ParserInterface::TYPE_DEFAULT, $chunkFilePath);
            } else {
                $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, $dryRunParams->getLimitRows());
            }

            $collection = $this->parsingFileService->formatNumbersAfterParser($collection, $connectedDataSource);

            $dataSet = $connectedDataSource->getDataSet();
            $rows = $collection->getRows();
            $columns = [];
            $firstReport = $rows->count() > 0 ? $rows[0] : array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
            foreach ($firstReport as $field => $value) {
                $columns[$field] = $field;
            }

            $dataTransferObject = [];
            $dataTransferObject['reports'] = $this->getArray($rows);
            $dataTransferObject['columns'] = $columns;
            $dataTransferObject['total'] = $rows->count();
            $dataTransferObject['average'] = [];
            $dataTransferObject['types'] = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
            $dataTransferObject['range'] = null;

            // executing sort and filter preview report data
            if (count($dryRunParams->getSearches()) > 0) {
                $dataTransferObject = $this->dryRunReportFilter->filterReports($dataTransferObject, $dryRunParams);
            }

            // TODO: if only parsing Data with limit one time, the report records after filtering may be less than the expected limit
            // TODO: simple solution: do parsing data again if not reach the limit
            // TODO: then finally do limit for final reports

            if ($dryRunParams->getSortField()) {
                $sortField = $dryRunParams->getSortField();
                $sortField = str_replace('"', '', $sortField);
                $dryRunParams->setSortField($sortField);

                if (!$dryRunParams->getOrderBy()) {
                    $dryRunParams->setOrderBy('asc');
                }

                $dataTransferObject = $this->dryRunReportSorter->sortReports($dataTransferObject, $dryRunParams);
            }

            /* set total report after filter */
            $dataTransferObject[self::DATA_TOTAL] = count($dataTransferObject[self::DATA_REPORTS]);

            /* return report result */
            return $dataTransferObject;
        } catch (ImportDataException $e) {
            $details = [
                AbstractConnectedDataSourceAlert::CODE => $e->getAlertCode(),
                AbstractConnectedDataSourceAlert::DETAILS => [
                    AbstractConnectedDataSourceAlert::KEY_COLUMN => $e->getColumn(),
                    AbstractConnectedDataSourceAlert::KEY_CONTENT => $e->getContent()
                ]
            ];

            throw new PublicImportDataException($details, $e);
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param $limit
     * @param string $parserType
     * @param null $chunkFilePath
     * @return Collection
     * @throws ImportDataException
     */
    private function parsingData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, $limit = null, $parserType = ParserInterface::TYPE_DEFAULT, $chunkFilePath = null)
    {
        /*
         * parsing data
         */
        if ($chunkFilePath) {
            $this->logger->notice(sprintf('begin parsing chunk "%s"', $chunkFilePath));
        } else {
            $this->logger->notice(sprintf('begin parsing file "%s"', $dataSourceEntry->getFileName()));
        }

        $this->parsingFileService->resetInjectParams();
        try {
            $collection = $this->parsingFileService->doParser($dataSourceEntry, $connectedDataSource, $limit, $parserType, $chunkFilePath);
        } catch (ImportDataException $ex) {
            throw $ex;
        }
        $this->logger->notice('parsing completed');

        if ($parserType != ParserInterface::TYPE_PRE_GROUPS) {
            return $this->parsingFileService->setDataOfColumnsNotMappedToNull($collection, $connectedDataSource);
        } else {
            return $collection;
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ImportHistoryInterface $importHistoryEntity
     * @param $collection
     */
    private function importToDataBase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity, $collection)
    {
        if (!$collection instanceof Collection) {
            return;
        }

        /* import data to database */
        $this->logger->notice(sprintf('begin loading file "%s" data to database "%s"', $dataSourceEntry->getFileName(), $connectedDataSource->getDataSet()->getName()));
        $collection = $this->importer->importParsedDataFromFileToDatabase($collection, $importHistoryEntity->getId(), $connectedDataSource, $dataSourceEntry->getReceivedDate());
        $this->updateDataSetTotalRowService->updateDataSetTotalRow($connectedDataSource->getDataSet()->getId());
        $this->updateDataSetTotalRowService->updateAllConnectedDataSourcesTotalRowInOneDataSet($connectedDataSource->getDataSet()->getId());

        /** Import collection to map builder configs */
        $dataSet = $connectedDataSource->getDataSet();
        $mapBuilderConfigs = $this->mapBuilderConfigManager->getByMapDataSet($dataSet);

        foreach ($mapBuilderConfigs as $mapBuilderConfig) {
            /** @var MapBuilderConfigInterface $mapBuilderConfig */
            $this->dataMappingService->importDataFromComponentDataSet($mapBuilderConfig, $collection);
        }
    }
}