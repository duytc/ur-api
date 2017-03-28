<?php

namespace UR\Service\Import;


use UR\Model\Core\DataSetInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
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

    function __construct(ParsingFileService $parsingFileService, ParsedDataImporter $importer)
    {
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
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
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry);
            $rows = $collection->getRows();

            if (count($rows) < 1) {
                return $this->parsingFileService->getNoDataRows($connectedDataSource->getDataSet()->getAllDimensionMetrics());
            }

            $this->parsingFileService->addTransformColumnAfterParsing($connectedDataSource->getTransforms());
            $rows = $this->parsingFileService->formatColumnsTransformsAfterParser($collection->getRows());

            return $rows;
        } catch (ImportDataException $e) {
            $details = [
                AbstractConnectedDataSourceAlert::COLUMN => $e->getColumn(),
                AbstractConnectedDataSourceAlert::CONTENT => $e->getContent()
            ];

            $result = [
                AbstractConnectedDataSourceAlert::CODE => $e->getAlertCode(),
                AbstractConnectedDataSourceAlert::DETAILS => $details
            ];

            $message = json_encode($result);

            throw new BadRequestHttpException($message);
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return \UR\Service\DTO\Collection
     */
    private function parsingData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry)
    {
        $allFields = $connectedDataSource->getDataSet()->getAllDimensionMetrics();

        /*
         * parsing data
         */
        $collection = $this->parsingFileService->doParser($dataSourceEntry, $connectedDataSource);
        $rows = $collection->getRows();

        //overwrite duplicate
        if ($connectedDataSource->getDataSet()->getAllowOverwriteExistingData()) {
            $rows = $this->parsingFileService->overrideDuplicate($rows, $connectedDataSource->getDataSet()->getDimensions());
        }

        return $this->parsingFileService->setDataOfColumnsNotMappedToNull($rows, $allFields);
    }
}