<?php

namespace UR\Worker\Job\Linear;

use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;
use UR\Service\DataSource\DataSourceCleaningService;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportDataLogger;
use UR\Service\Import\ImportHistoryService;
use UR\Worker\Job\Concurrent\ParseChunkFile;
use UR\Worker\Manager;

class LoadFileIntoDataSetSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'loadFileIntoDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';
    const TOTAL_CHUNK_KEY_TEMPLATE = 'import_history_%d_total_chunk';
    const CHUNKS_KEY_TEMPLATE = 'import_history_%d_chunks';
    const CHUNK_FAILED_KEY_TEMPLATE = 'import_history_%d_failed';
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

    private $dataSourceFileFactory;

    private $fileSizeThreshold;

    private $tempFileDir;

    private $redis;

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
        DataSourceCleaningService $dataSourceCleaningService,
        DataSourceFileFactory $dataSourceFileFactory,
        Redis $redis,
        $fileSizeThreshold,
        $tempFileDir
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
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->fileSizeThreshold = $fileSizeThreshold;
        $this->tempFileDir = $tempFileDir;
        $this->redis = $redis;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $isImportFail = false;
        $importHistoryEntity = null;
        // do something here
        $dataSourceEntryId = (int)$params->getRequiredParam(self::ENTRY_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
        $alert = null;

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
            $fileSize = filesize($this->dataSourceFileFactory->getAbsolutePath($dataSourceEntry->getPath()));
            if ($fileSize > $this->fileSizeThreshold || $dataSourceEntry->isSeparable()) {
                $this->logger->notice('File is too big, split into small chunks..');
                $dataSourceEntry->setSeparable(true);
                $chunks = $dataSourceEntry->getChunks();
                if (empty($chunks)) {
                    $dataSourceEntry = $this->dataSourceFileFactory->splitHugeFile($dataSourceEntry);
                    $this->logger->notice('Splitting completed');
                }

                $this->dataSourceEntryManager->save($dataSourceEntry);
                /* get chunks again with old data */
                $chunks = $dataSourceEntry->getChunks();
                $totalChunkKey = sprintf(self::TOTAL_CHUNK_KEY_TEMPLATE, $importHistoryEntity->getId());
                $this->redis->set($totalChunkKey, count($chunks));

                $chunksKey = sprintf(self::CHUNKS_KEY_TEMPLATE, $importHistoryEntity->getId());
                $chunkFailedKey = sprintf(self::CHUNK_FAILED_KEY_TEMPLATE, $importHistoryEntity->getId());
                $this->logger->notice('create jobs for parsing chunks');
                $this->redis->del(sprintf(ParseChunkFile::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId));
                $this->redis->del($chunkFailedKey);
                foreach ($chunks as $key => $chunk) {
                    $randomName = sprintf("import_%s_part_%s_random_%s.csv", $importHistoryEntity->getId(), $key, uniqid((new \DateTime())->format('Y-m-d'), true));
                    $outputFilePath = join('/', array($this->tempFileDir, $randomName));
                    $this->redis->rPush($chunksKey, $outputFilePath);
                    $this->workerManager->parseChunkFile($connectedDataSourceId, $dataSourceEntryId, $importHistoryEntity->getId(), $chunk, $outputFilePath, $totalChunkKey, $chunksKey);
                }

                $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource, $importHistoryEntity);

                $importHistoryIds = array_map(function (ImportHistoryInterface $importHistory) {
                    return $importHistory->getId();
                }, $importHistories);

                $this->importHistoryManager->deleteImportHistoriesByIds($importHistoryIds);
                $this->importHistoryService->deleteImportedDataByImportHistories($importHistoryIds, $connectedDataSource->getDataSet()->getId());
                return;
            }

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
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, null);

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
            $isImportFail = true;
            $this->importDataLogger->doImportLogging($e->getAlertCode(), $publisherId, $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $e);
        } catch (\Exception $exception) {
            $isImportFail = true;
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $exception);
        } finally {
            $this->logger->notice('----------------------------LOADING COMPLETED-------------------------------------------------------------');

            if ($isImportFail && $importHistoryEntity instanceof ImportHistoryInterface) {
                /*delete import history when fail, rollback data*/
                $this->importHistoryManager->deleteImportHistoriesByIds([$importHistoryEntity->getId()]);
            }

            if ($alert instanceof ConnectedDataSourceAlertInterface) {
                $this->workerManager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
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