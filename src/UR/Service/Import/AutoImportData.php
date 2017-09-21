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
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\ParsedDataImporter;
use UR\Service\DTO\Collection;
use UR\Service\Parser\DryRunReportFilterInterface;
use UR\Service\Parser\DryRunReportSorterInterface;
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

    function __construct(ParsingFileService $parsingFileService, ParsedDataImporter $importer, Logger $logger, DryRunReportSorterInterface $dryRunReportSorter, DryRunReportFilterInterface $dryRunReportFilter, MapBuilderConfigManager $mapBuilderConfigManager, DataMappingService $dataMappingService)
    {
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
        $this->logger = $logger;
        $this->dryRunReportSorter = $dryRunReportSorter;
        $this->dryRunReportFilter = $dryRunReportFilter;
        $this->mapBuilderConfigManager = $mapBuilderConfigManager;
        $this->dataMappingService = $dataMappingService;
    }

    /**
     * @inheritdoc
     */
    public function loadingDataFromFileToDatabase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry);

        /* import data to database */
        $this->logger->notice(sprintf('begin loading file "%s" data to database "%s"', $dataSourceEntry->getFileName(), $connectedDataSource->getDataSet()->getName()));
        $collection = $this->importer->importParsedDataFromFileToDatabase($collection, $importHistoryEntity->getId(), $connectedDataSource, $dataSourceEntry->getReceivedDate());

        if (!($collection instanceof Collection)) {
            return;
        }

        /** Import collection to map builder configs */
        $dataSet = $connectedDataSource->getDataSet();
        $mapBuilderConfigs = $this->mapBuilderConfigManager->getByMapDataSet($dataSet);

        foreach ($mapBuilderConfigs as $mapBuilderConfig) {
            /** @var MapBuilderConfigInterface $mapBuilderConfig */
            $this->dataMappingService->importDataFromComponentDataSet($mapBuilderConfig, $collection);
        }
    }

    /**
     * @inheritdoc
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, DryRunParamsInterface $dryRunParams)
    {
        try {
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, $dryRunParams->getLimitRows());

            $this->parsingFileService->addTransformColumnAfterParsing($connectedDataSource->getTransforms());
            $this->parsingFileService->formatColumnsTransformsAfterParser($collection);

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
     * @return \UR\Service\DTO\Collection
     * @throws ImportDataException
     */
    private function parsingData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, $limit = null)
    {
        $allFields = $connectedDataSource->getDataSet()->getAllDimensionMetrics();

        /*
         * parsing data
         */
        $this->logger->notice(sprintf('begin parsing file "%s"', $dataSourceEntry->getFileName()));
        $this->parsingFileService->resetInjectParams();
        $collection = $this->parsingFileService->doParser($dataSourceEntry, $connectedDataSource, $limit);
        $this->logger->notice('parsing file completed');
        $rows = $collection->getRows();

        return $this->parsingFileService->setDataOfColumnsNotMappedToNull($rows, $allFields);
    }

    /**
     * @param mixed $rows
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     */
    private function removeRowsNotMapRequiresFields($rows, $connectedDataSource)
    {
        if (!is_array($rows)) {
            return [];
        }

        $requireFields = $connectedDataSource->getRequires();
        if (!is_array($requireFields)) {
            return $rows;
        }

        $requireFields = array_values($requireFields);

        if (empty($requireFields)) {
            return $rows;
        }

        $dataSet = $connectedDataSource->getDataSet();
        $types = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());

        foreach ($requireFields as $requireField) {
            if (!array_key_exists($requireField, $types)) {
                continue;
            }
            $type = $types[$requireField];

            foreach ($rows as $index => &$row) {
                if (!array_key_exists($requireField, $row)) {
                    continue;
                }
                $value = FieldType::convertValue($row[$requireField], $type);

                if ($type == FieldType::TEXT || $type == FieldType::LARGE_TEXT || $type == FieldType::DATE || $type == FieldType::DATETIME) {
                    if (empty($value)) {
                        unset($rows[$index]);
                    }
                    continue;
                }

                if ($type == FieldType::NUMBER || $type == FieldType::DECIMAL) {
                    if ($value == null) {
                        unset($rows[$index]);
                    }
                    continue;
                }
            }
        }

        return $rows;
    }
}