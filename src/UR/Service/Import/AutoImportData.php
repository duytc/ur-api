<?php

namespace UR\Service\Import;


use Monolog\Logger;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\ParsedDataImporter;
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

    private $logger;

    function __construct(ParsingFileService $parsingFileService, ParsedDataImporter $importer, Logger $logger)
    {
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
        $this->logger = $logger;
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
        $this->importer->importParsedDataFromFileToDatabase($collection, $importHistoryEntity->getId(), $connectedDataSource, $dataSourceEntry->getReceivedDate());
    }

    /**
     * @inheritdoc
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, $limitRows)
    {
        try {
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry, $limitRows);

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
            $dataTransferObject['total'] = count($rows);
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
        $this->logger->notice(sprintf('begin parsing file "%s"', $dataSourceEntry->getFileName()));
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