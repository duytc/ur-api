<?php

namespace UR\Service\Import;


use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\Alert\ConnectedDataSource\DataAddedAlert;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\DataSet\ParsedDataImporter;
use UR\Service\Parser\ParsingFileService;
use UR\Worker\Manager;

class AutoImportData implements AutoImportDataInterface
{
    /**
     * @var ParsedDataImporter
     */
    private $importer;

    /** @var Manager */
    private $workerManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var ImportDataLogger
     */
    private $logger;

    private $parsingFileService;

    private $connectedDataSourceAlertFactory;

    function __construct(Manager $workerManager, ImportHistoryManagerInterface $importHistoryManager, ImportDataLogger $logger, ParsingFileService $parsingFileService, ParsedDataImporter $importer)
    {
        $this->workerManager = $workerManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->logger = $logger;
        $this->parsingFileService = $parsingFileService;
        $this->importer = $importer;
        $this->connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
    }

    public function loadingDataFromFileToDatabase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry)
    {
        if ($connectedDataSource->getDataSet() === null) {
            $this->logger->doExceptionLogging('not found data set with this id');
            throw new InvalidArgumentException('not found data set with this id');
        }

        $publisherId = $connectedDataSource->getDataSource()->getPublisherId();
        $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntry($dataSourceEntry, $connectedDataSource->getDataSet());
        $importHistoryEntity = new ImportHistory();

        try {
            /* parsing data */
            $collection = $this->parsingData($connectedDataSource, $dataSourceEntry);

            /* creating import history */
            $this->createImportHistory($importHistoryEntity, $dataSourceEntry, $connectedDataSource);

            /* import data to database */
            $this->importer->importParsedDataFromFileToDatabase($collection, $importHistoryEntity->getId(), $connectedDataSource);

            /* alert when successful*/
            $importSuccessAlert = $this->connectedDataSourceAlertFactory->getAlert(
                $importHistoryEntity->getId(),
                $connectedDataSource->getAlertSetting(),
                DataAddedAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );

            if ($importSuccessAlert !== null) {
                $this->workerManager->processAlert($importSuccessAlert->getAlertCode(), $publisherId, $importSuccessAlert->getDetails());
            }

            $this->importHistoryManager->deletePreviousImports($importHistories);
        } catch (ImportDataException $e) { /* exception */
            $errorCode = $e->getAlertCode();
            if ($e->getAlertCode() === null) {
                $errorCode = ImportFailureAlert::ALERT_CODE_UN_EXPECTED_ERROR;
                if ($importHistoryEntity->getId() !== null) {
                    $this->importHistoryManager->delete($importHistoryEntity);
                }
                $message = sprintf("data-set#%s data-source#%s data-source-entry#%s (message: %s)", $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getMessage());
                $this->logger->doExceptionLogging($message);
            } else {
                $this->logger->doImportLogging($e->getAlertCode(), $connectedDataSource->getDataSet()->getId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
            }

            $failureAlert = $this->connectedDataSourceAlertFactory->getAlert(
                null,
                $connectedDataSource->getAlertSetting(),
                $errorCode, $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                $e->getColumn(),
                $e->getRow(),
                $e->getContent()
            );

            /* unexpected error */
            if ($failureAlert != null) {
                $this->workerManager->processAlert($e->getAlertCode(), $publisherId, $failureAlert->getDetails());
            }
        }
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
                return $this->parsingFileService->getNoDataRows($this->getAllDataSetFields($connectedDataSource));
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

    private function parsingData(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry)
    {
        $allFields = $this->getAllDataSetFields($connectedDataSource);

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

    private function createImportHistory(ImportHistoryInterface $importHistoryEntity, DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource)
    {
        $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
        $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
        $this->importHistoryManager->save($importHistoryEntity);
    }

    private function getAllDataSetFields(ConnectedDataSourceInterface $connectedDataSource)
    {
        $dimensions = $connectedDataSource->getDataSet()->getDimensions();
        $metrics = $connectedDataSource->getDataSet()->getMetrics();
        return array_merge($dimensions, $metrics);
    }
}