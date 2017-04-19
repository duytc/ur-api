<?php

namespace UR\Service\Import;


use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\DataSet\ParsedDataImporter;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\Parser\ParsingFileService;

class AutoImportData implements AutoImportDataInterface
{
    /**
     * @var ParsedDataImporter
     */
    private $importer;

    /**
     * @var ParsingFileService
     */
    private $parsingFileService;

    /**
     * @var integer
     */
    private $maxDryRunSize;

    function __construct(ParsingFileService $parsingFileService, ParsedDataImporter $importer, $maxDryRunSize)
    {
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
        $this->maxDryRunSize = $maxDryRunSize;
    }

    /**
     * @inheritdoc
     */
    public function loadingDataFromFileToDatabase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity)
    {
        /* parsing data */
        $collection = $this->parsingData($connectedDataSource, $dataSourceEntry);

        /* import data to database */
        $this->importer->importParsedDataFromFileToDatabase($collection, $importHistoryEntity->getId(), $connectedDataSource);
    }

    /**
     * @inheritdoc
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry)
    {
        try {
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, $this->maxDryRunSize);

            $this->parsingFileService->addTransformColumnAfterParsing($connectedDataSource->getTransforms());
            $rows = $this->parsingFileService->formatColumnsTransformsAfterParser($collection->getRows());

            $dataSet = $connectedDataSource->getDataSet();

            $columns = [];
            $firstReport = count($rows) > 0 ? current($rows) : array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
            foreach ($firstReport as $field => $value) {
                $columns[$field] = $field;
            }

            $dataTransferObject = [];
            $dataTransferObject['reports'] = $rows;
            $dataTransferObject['columns'] = $columns;
            $dataTransferObject['total'] = count ($rows);
            $dataTransferObject['average'] = [];
            $dataTransferObject['types'] = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
            $dataTransferObject['range'] = null;

            return $dataTransferObject;
        } catch (ImportDataException $e) {
            $details = [
                AbstractConnectedDataSourceAlert::CODE => $e->getAlertCode(),
                AbstractConnectedDataSourceAlert::DETAILS => [
                    AbstractConnectedDataSourceAlert::COLUMN => $e->getColumn(),
                    AbstractConnectedDataSourceAlert::CONTENT => $e->getContent()
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
        $collection = $this->parsingFileService->doParser($dataSourceEntry, $connectedDataSource, $limit);
        $rows = $collection->getRows();

        return $this->parsingFileService->setDataOfColumnsNotMappedToNull($rows, $allFields);
    }
}