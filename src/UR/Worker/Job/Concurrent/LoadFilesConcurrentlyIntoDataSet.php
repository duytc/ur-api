<?php

namespace UR\Worker\Job\Concurrent;


use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\Job\LinearWithConcurrentJobInterface;
use Pubvantage\Worker\JobCounterInterface;
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
use UR\Service\Import\LoadingStatusCheckerInterface;
use UR\Worker\Manager;

class LoadFilesConcurrentlyIntoDataSet implements ExpirableJobInterface, LinearWithConcurrentJobInterface
{
    const LOAD_FILE_CONCURRENT_EXIT_CODE_WAITING = 111;
    const LOAD_FILE_CONCURRENT_EXIT_CODE_FAILED = 0;
    const JOB_NAME = 'loadFilesConcurrentlyIntoDataSet';

    const DATA_SET_ID = 'data_set_id';
    const TOTAL_CHUNK_KEY_TEMPLATE = 'import_history_%d_chunks_total';
    const CHUNKS_KEY_TEMPLATE = 'import_history_%d_chunks';
    const CHUNK_FAILED_KEY_TEMPLATE = 'import_history_%d_failed';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const ENTRY_ID = 'entry_id';
    const IMPORT_HISTORY_ID = 'import_history_id';
    const CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY = 'concurrent_loading_file_count_redis_key';
    const CONCURRENT_LOADING_FILE_UNIQUE_ID = 'unique_id';

    /** @var DataSourceCleaningService */
    protected $dataSourceCleaningService;

    /** @var LoggerInterface */
    private $logger;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    /** @var  ImportHistoryService */
    private $importHistoryService;

    /** @var  AutoImportDataInterface */
    private $importData;

    /** @var  Manager */
    private $workerManager;

    /** @var  ImportDataLogger */
    private $importDataLogger;

    /** @var EntityManager */
    private $entityManager;

    /** @var  DataSourceFileFactory */
    private $dataSourceFileFactory;

    private $fileSizeThreshold;

    private $tempFileDir;

    /** @var  Redis */
    private $redis;

    /** @var  JobCounterInterface */
    private $jobCounter;

    private $lockKeyPrefix;

    /** @var LoadingStatusCheckerInterface */
    private $loadingStatusChecker;

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
        $tempFileDir,
        JobCounterInterface $jobCounter,
        $lockKeyPrefix,
        LoadingStatusCheckerInterface $loadingStatusChecker
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
        $this->jobCounter = $jobCounter;
        $this->lockKeyPrefix = $lockKeyPrefix;
        $this->loadingStatusChecker = $loadingStatusChecker;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        /* get params from jobParams */
        $importHistoryId = (int)$params->getRequiredParam(self::IMPORT_HISTORY_ID);
        $dataSourceEntryId = (int)$params->getRequiredParam(self::ENTRY_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $concurrentLoadingFileCountRedisKey = $params->getRequiredParam(self::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY);

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        $importHistoryEntity = $this->importHistoryManager->find($importHistoryId);

        /* check params */
        try {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                throw new \Exception(sprintf('[LoadFilesConcurrentlyIntoDataSet] error occur: Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            }

            /**@var ConnectedDataSourceInterface $connectedDataSource */
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                throw new \Exception(sprintf('[LoadFilesConcurrentlyIntoDataSet] error occur: Connected Data Source %d not found (may be deleted before)', $connectedDataSourceId));
            }

            if (!$importHistoryEntity instanceof ImportHistoryInterface) {
                throw new \Exception(sprintf('[LoadFilesConcurrentlyIntoDataSet] error occur: Import history %d not found (may be deleted before)', $importHistoryId));
            }
        } catch (\Exception $e) {
            $this->logger->warning('[LoadFilesConcurrentlyIntoDataSet] got exception ' . $e->getMessage());

            if ($importHistoryEntity instanceof ImportHistoryInterface) {
                $this->importHistoryManager->deleteImportHistoriesByIds([$importHistoryEntity->getId()]);
            }

            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            if ($importHistoryEntity instanceof ImportHistoryInterface) {
                $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $e);
                $this->workerManager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
            }
            
            if ($connectedDataSource instanceof ConnectedDataSourceInterface) {
                $this->loadingStatusChecker->postFileLoadingCompletedForConnectedDatSource($connectedDataSource, $concurrentLoadingFileCountRedisKey);
                $this->loadingStatusChecker->postFileLoadingCompletedForDataSet($connectedDataSource->getDataSet());
            }

            return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
        }

        /* if large file => handle large file by parsing chunk files, ... */
        $fileSize = filesize($this->dataSourceFileFactory->getAbsolutePath($dataSourceEntry->getPath()));
        if ($fileSize > $this->fileSizeThreshold || $dataSourceEntry->isSeparable()) {
            $this->loadLargeFile($importHistoryEntity, $concurrentLoadingFileCountRedisKey);
            
            return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
        }

        /* handle normal file */
        $this->loadNormalFile($importHistoryEntity);

        $this->loadingStatusChecker->postFileLoadingCompletedForConnectedDatSource($connectedDataSource, $concurrentLoadingFileCountRedisKey);
        $this->loadingStatusChecker->postFileLoadingCompletedForDataSet($connectedDataSource->getDataSet());
        
        return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
    }

    /**
     * load Large File
     *
     * @param ImportHistoryInterface $importHistoryEntity
     * @param $concurrentLoadingFilesCountRedisKey
     * @return int
     */
    private function loadLargeFile(ImportHistoryInterface $importHistoryEntity, $concurrentLoadingFilesCountRedisKey)
    {
        $this->logger->info('[LoadFilesConcurrentlyIntoDataSet] loading large file');

        $dataSourceEntry = $importHistoryEntity->getDataSourceEntry();
        $connectedDataSource = $importHistoryEntity->getConnectedDataSource();

        try {
            $this->logger->notice('[LoadFilesConcurrentlyIntoDataSet] File is too big, split into small chunks...');
            $dataSourceEntry->setSeparable(true);
            $chunks = $dataSourceEntry->getChunks();
            if (empty($chunks)) {
                $dataSourceEntry = $this->dataSourceFileFactory->splitHugeFile($dataSourceEntry);
                $this->logger->notice('[LoadFilesConcurrentlyIntoDataSet] Splitting completed');
            }

            $this->dataSourceEntryManager->save($dataSourceEntry);
            /* get chunks again with old data */
            $chunks = $dataSourceEntry->getChunks();
            $totalChunkKey = sprintf(self::TOTAL_CHUNK_KEY_TEMPLATE, $importHistoryEntity->getId());
            $this->redis->set($totalChunkKey, count($chunks));

            $chunksKey = sprintf(self::CHUNKS_KEY_TEMPLATE, $importHistoryEntity->getId());
            $chunkFailedKey = sprintf(self::CHUNK_FAILED_KEY_TEMPLATE, $importHistoryEntity->getId());
            $this->logger->notice('[LoadFilesConcurrentlyIntoDataSet] create jobs for parsing chunks');
            $this->redis->del(sprintf(ParseChunkFile::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntry->getId()));
            $this->redis->del($chunkFailedKey);

            $jobs = [];

            foreach ($chunks as $key => $chunk) {
                $randomName = sprintf("import_%s_part_%s_random_%s.csv", $importHistoryEntity->getId(), $key, uniqid((new \DateTime())->format('Y-m-d'), true));
                $outputFilePath = join('/', array($this->tempFileDir, $randomName));
                $this->redis->rPush($chunksKey, $outputFilePath);

                $jobs[] = [
                    'task' => ParseChunkFile::JOB_NAME,
                    ParseChunkFile::DATA_SET_ID => $connectedDataSource->getDataSet()->getId(),
                    ParseChunkFile::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
                    ParseChunkFile::INPUT_FILE_PATH => $chunk,
                    ParseChunkFile::OUTPUT_FILE_PATH => $outputFilePath,
                    ParseChunkFile::DATA_SOURCE_ENTRY_ID => $dataSourceEntry->getId(),
                    ParseChunkFile::TOTAL_CHUNK_KEY => $totalChunkKey,
                    ParseChunkFile::CHUNKS_KEY => $chunksKey,
                    ParseChunkFile::IMPORT_HISTORY_ID => $importHistoryEntity->getId(),
                    self::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY => $concurrentLoadingFilesCountRedisKey,
                ];
            }

            $this->workerManager->parseChunkFile($jobs);

            return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
        } catch (\Exception $exception) {
            $this->logger->warning('[LoadFilesConcurrentlyIntoDataSet] loading large file got exception ', $exception->getMessage());

            if ($importHistoryEntity instanceof ImportHistoryInterface) {
                $this->importHistoryManager->deleteImportHistoriesByIds([$importHistoryEntity->getId()]);
            }

            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $exception);
            if ($alert instanceof ConnectedDataSourceAlertInterface) {
                $this->workerManager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
            }
        }

        return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
    }

    /**
     * load Normal File
     *
     * @param ImportHistoryInterface $importHistoryEntity
     * @return int
     */
    private function loadNormalFile(ImportHistoryInterface $importHistoryEntity)
    {
        $this->logger->info('[LoadFilesConcurrentlyIntoDataSet] loading normal file');

        $dataSourceEntry = $importHistoryEntity->getDataSourceEntry();
        $connectedDataSource = $importHistoryEntity->getConnectedDataSource();

        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
        $isImportFail = false;

        try {
            /* call service load data to database */
            $this->importData->loadingDataFromFileToDatabase($connectedDataSource, $dataSourceEntry, $importHistoryEntity);
            $this->importHistoryManager->deleteOldImportHistories($importHistoryEntity);
            
            /* alert when successful*/
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, null);

            $this->removeDuplicatedDateEntries($dataSourceEntry);
        } catch (ImportDataException $e) {
            $isImportFail = true;
            $this->importDataLogger->doImportLogging($e->getAlertCode(), $connectedDataSource->getDataSet()->getPublisherId(), $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $e);
        } catch (\Exception $exception) {
            $isImportFail = true;
            $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistoryEntity, $exception);
        }

        if ($isImportFail) {
            $this->logger->notice('[LoadFilesConcurrentlyIntoDataSet] ----------------------------LOADING FAILED----------------------------------');
        } else {
            $this->logger->notice('[LoadFilesConcurrentlyIntoDataSet] ----------------------------LOADING COMPLETED-------------------------------');
        }

        if ($isImportFail && $importHistoryEntity instanceof ImportHistoryInterface) {
            $this->importHistoryManager->deleteImportHistoriesByIds([$importHistoryEntity->getId()]);
        }

        if ($alert instanceof ConnectedDataSourceAlertInterface) {
            $this->workerManager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
        }

        return ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
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

    public static function createUniqueId()
    {
        return $tokenString = bin2hex(random_bytes(18));
    }
}