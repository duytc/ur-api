<?php

namespace UR\Worker\Job\Linear;

use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\DataSource\DataSourceCleaningService;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportDataLogger;
use UR\Service\Import\ImportHistoryService;
use UR\Worker\Manager;

class LoadFileIntoDataSetSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'loadFileIntoDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';

    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const ENTRY_ID = 'entry_id';

    /** @var DataSourceCleaningService  */
    protected $dataSourceCleaningService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    private $importHistoryService;

    private $importData;

    private $workerManager;

    private $importDataLogger;

    private $entityManager;

    public function __construct(
        LoggerInterface $logger,
        DataSourceEntryManagerInterface $dataSourceEntryManager,
        ConnectedDataSourceManagerInterface $connectedDataSourceManager,
        ImportHistoryManagerInterface $importHistoryManager,
        ImportHistoryService $importHistoryService,
        AutoImportDataInterface $importData,
        Manager $workerManager,
        ImportDataLogger $importDataLogger,
        EntityManager $entityManager,
        DataSourceCleaningService $dataSourceCleaningService
    )
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->importHistoryService = $importHistoryService;
        $this->importData = $importData;
        $this->workerManager = $workerManager;
        $this->importDataLogger = $importDataLogger;
        $this->entityManager = $entityManager;
        $this->dataSourceCleaningService = $dataSourceCleaningService;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $isImportFail = false;
        $errorCode = AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY;
        $errorRow = null;
        $errorColumn = null;
        $errorContent = null;
        $importHistoryEntity = null;
        // do something here
        $dataSourceEntryId = (int)$params->getRequiredParam(self::ENTRY_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
        try {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                throw new Exception(sprintf('error occur: Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            }

            /**@var ConnectedDataSourceInterface $connectedDataSource */
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                throw new Exception(sprintf('error occur: Connected Data Source %d not found (may be deleted before)', $connectedDataSourceId));
            }

            /* creating import history */
            try {
                $importHistoryEntity = $this->importHistoryManager->createImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);
            } catch (\Exception $exception) {
                throw new Exception(sprintf('Cannot create importHistory, error occur: %s', $exception->getMessage()));
            }

            $publisherId = $connectedDataSource->getDataSet()->getPublisherId();
            $this->logger->notice(
                sprintf('starting to import file "%s" into data set "%s" (entry: %d, data set %d, connected data source: %d, data source: %d)',
                    $dataSourceEntry->getFileName(),
                    $connectedDataSource->getDataSet()->getName(),
                    $dataSourceEntry->getId(),
                    $connectedDataSource->getDataSet()->getId(),
                    $connectedDataSource->getId(),
                    $connectedDataSource->getDataSource()->getId()
                )
            );

            /* call service load data to data base */
            $this->importData->loadingDataFromFileToDatabase($connectedDataSource, $dataSourceEntry, $importHistoryEntity);

            /* alert when successful*/
            $importSuccessAlert = $connectedDataSourceAlertFactory->getAlert(
                $importHistoryEntity->getId(),
                $connectedDataSource->getAlertSetting(),
                AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );

            if ($importSuccessAlert !== null) {
                $this->workerManager->processAlert($importSuccessAlert->getAlertCode(), $publisherId, $importSuccessAlert->getDetails(), $importSuccessAlert->getDataSourceId());
            }

//             delete all previous import histories that have same data source entry id and data set
//             get import histories by data source entry and data set
            $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource, $importHistoryEntity);

            $importHistoryIds = array_map(function (ImportHistoryInterface $importHistory) {
                return $importHistory->getId();
            }, $importHistories);

            $this->importHistoryManager->deleteImportHistoriesByIds($importHistoryIds);
            $this->importHistoryService->deleteImportedDataByImportHistories($importHistoryIds, $connectedDataSource->getDataSet()->getId());

            $this->logger->notice(
                sprintf('success importing file "%s" into data set "%s" (entry: %d, data set %d, connected data source: %d, data source: %d)',
                    $dataSourceEntry->getFileName(),
                    $connectedDataSource->getDataSet()->getName(),
                    $dataSourceEntry->getId(),
                    $connectedDataSource->getDataSet()->getId(),
                    $connectedDataSource->getId(),
                    $connectedDataSource->getDataSource()->getId()
                )
            );

            $this->removeDuplicatedDateEntries($dataSourceEntry);

        } catch (ImportDataException $e) { /* exception */
            $errorCode = $e->getAlertCode();
            $isImportFail = true;
            $this->importDataLogger->doImportLogging($errorCode, $publisherId, $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
            $errorRow = $e->getRow();
            $errorColumn = $e->getColumn();
            $errorContent = $e->getContent();
        } catch (\Exception $exception) {
            $errorCode = AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR;
            $isImportFail = true;
            if ($dataSourceEntry instanceof DataSourceEntryInterface) {
                $message = sprintf('failed to import file "%" into data set "%s" (entry: %d, data set %d, connected data source: %d, data source: %d, message: %s)',
                    $dataSourceEntry->getFileName(),
                    $connectedDataSource->getDataSet()->getName(),
                    $dataSourceEntry->getId(),
                    $connectedDataSource->getDataSet()->getId(),
                    $connectedDataSource->getId(),
                    $connectedDataSource->getDataSource()->getId(),
                    $exception->getMessage()
                );
            }
            $this->logger->error($message);
        } finally {
            $this->logger->notice('----------------------------LOADING COMPLETED-------------------------------------------------------------');

            if ($isImportFail && $importHistoryEntity instanceof ImportHistoryInterface) {
                $failureAlert = $connectedDataSourceAlertFactory->getAlert(
                    $importHistoryEntity->getId(),
                    $connectedDataSource->getAlertSetting(),
                    $errorCode, $dataSourceEntry->getFileName(),
                    $connectedDataSource->getDataSource(),
                    $connectedDataSource->getDataSet(),
                    $errorColumn,
                    $errorRow,
                    $errorContent
                );

                /*delete import history when fail*/
                $this->importHistoryManager->deleteImportHistoriesByIds([$importHistoryEntity->getId()]);

                if ($failureAlert != null) {
                    $this->workerManager->processAlert($errorCode, $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails(), $failureAlert->getDataSourceId());
                }
            }

            $this->entityManager->clear();
            gc_collect_cycles();
        }
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function removeDuplicatedDateEntries(DataSourceEntryInterface $dataSourceEntry)
    {
        if (!$dataSourceEntry->getRemoveHistory()) {
            return;
        }

        $dataSource = $dataSourceEntry->getDataSource();

        /** Find all connected data sources related to a data source*/
        $connectedDataSources = $this->connectedDataSourceManager->getConnectedDataSourceByDataSource($dataSource);

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $newImportHistories = $this->importHistoryManager->findImportHistoriesByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);
            
            if (count($newImportHistories) < 1) {
                /** Wait for other worker job load files in to data set */
                return;
            }
        }

        /** Make sure all connected data sources have new import history with new data source entry */
        $this->dataSourceCleaningService->removeDuplicatedDateEntries($dataSourceEntry->getDataSource());
    }
}