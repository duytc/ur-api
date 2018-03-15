<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\RedLock;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use Symfony\Component\Filesystem\Filesystem;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;
use UR\Service\DataSource\MergeFiles;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\LoadingStatusCheckerInterface;
use UR\Worker\Manager;

class ParseChunkFile implements JobInterface
{
    const DATA_SET_ID = 'data_set_id';
    const DATA_SOURCE_ENTRY_ID = 'data_source_entry_id';
    const IMPORT_HISTORY_ID = 'import_history_id';
    const JOB_NAME = 'parse_chunk_file';
    const INPUT_FILE_PATH = 'input_file_path';
    const OUTPUT_FILE_PATH = 'output_file_path';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const TOTAL_CHUNK_KEY = 'parse_chunk_file:total';
    const CHUNK_FAILED_KEY_TEMPLATE = 'import_history_%d_failed';
    const CHUNKS_KEY = 'parse_chunk_file:chunks';
    const PROCESS_ALERT_KEY_TEMPLATE = 'data_source_entry_%d_alert';
    const FLAG_IMPORT_COMPLETED_CHUNKS = "import_history_%s_chunks_finish";

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Manager */
    private $manager;

    /** @var RedLock */
    private $redLock;

    /** @var Redis */
    private $redis;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var DataSourceEntryManagerInterface */
    private $connectedDataSourceManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    /** @var AutoImportDataInterface */
    private $autoImportData;
    private $tempFileDirectory;
    private $uploadFileDirectory;

    private $lockKeyPrefix;

    /**
     * @var JobCounterInterface
     */
    private $jobCounter;

    /** @var LoadingStatusCheckerInterface */
    private $loadingStatusChecker;

    public function __construct(LoggerInterface $logger,
                                Manager $manager,
                                Redis $redis,
                                DataSourceEntryManagerInterface $dataSourceEntryManager,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager,
                                AutoImportDataInterface $autoImportData,
                                ImportHistoryManagerInterface $importHistoryManager,
                                $tempFileDirectory,
                                $uploadFileDirectory,
                                $lockKeyPrefix,
                                JobCounterInterface $jobCounter,
                                LoadingStatusCheckerInterface $loadingStatusChecker
    )
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->redis = $redis;
        $this->redLock = new RedLock([$redis]);
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->autoImportData = $autoImportData;
        $this->importHistoryManager = $importHistoryManager;
        $this->tempFileDirectory = $tempFileDirectory;
        $this->uploadFileDirectory = $uploadFileDirectory;
        $this->lockKeyPrefix = $lockKeyPrefix;
        $this->jobCounter = $jobCounter;
        $this->loadingStatusChecker = $loadingStatusChecker;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        /* get params from jobParams */
        $importHistoryId = (int)$params->getRequiredParam(self::IMPORT_HISTORY_ID);
        $chunkFailedKey = sprintf(self::CHUNK_FAILED_KEY_TEMPLATE, $importHistoryId);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $dataSourceEntryId = (int)$params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);
        $concurrentLoadingFilesCountRedisKey = $params->getRequiredParam(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY);

        // if one failed previously, all failed
        $failed = $this->redis->exists($chunkFailedKey);
        if ($failed == true) {
            $this->handleParseChunkFileFailed($params);

            return;
        }

        try {
            $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                throw new \Exception(sprintf('[ParseChunkFile] ConnectedDataSource %d not found or you do not have permission', $connectedDataSourceId));
            }

            $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                throw new \Exception(sprintf('[ParseChunkFile] DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            }

            $importHistory = $this->importHistoryManager->find($importHistoryId);
            if (!$importHistory instanceof ImportHistoryInterface) {
                throw new \Exception(sprintf('[ParseChunkFile] ImportHistory %d not found or you do not have permission', $importHistoryId));
            }
        } catch (\Exception $e) {
            $this->logger->error($e);

            // set error for chunk in redis
            // this will let other parseChunkFile jobs know, then do "if one failed previously, all failed" as above
            $this->redis->set($chunkFailedKey, 1);

            $this->handleParseChunkFileFailed($params);

            return;
        }

        $inputFile = $params->getRequiredParam(self::INPUT_FILE_PATH);
        $outputFile = $params->getRequiredParam(self::OUTPUT_FILE_PATH);

        $transforms = $connectedDataSource->getTransforms();
        $hasGroup = false;
        foreach ($transforms as $transform) {
            if (is_array($transform) && array_key_exists('type', $transform) && in_array($transform['type'], ['groupBy', 'subsetGroup'])) {
                $hasGroup = true;
                break;
            }
        }

        try {
            if (!$hasGroup) {
                $this->autoImportData->parseFileThenInsert($connectedDataSource, $dataSourceEntry, $importHistory, $inputFile);
            } else {
                $directory = pathinfo($outputFile, PATHINFO_DIRNAME);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                $this->autoImportData->parseFileOnPreGroups($connectedDataSource, $dataSourceEntry, $importHistory, $inputFile, $outputFile);
            }
        } catch (ImportDataException $ex) {
            $this->handleException($importHistory, $ex, $params, $chunkFailedKey);
        } catch (\Exception $ex) {
            $this->handleException($importHistory, $ex, $params, $chunkFailedKey);
        }

        // all chunk parsed
        if ($this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY)) == 0) {
            $importHistoryLockKey = sprintf(ParseChunkFile::FLAG_IMPORT_COMPLETED_CHUNKS, $importHistory->getId());

            if (!$this->redis->exists($importHistoryLockKey)) {
                // Set lock to make sure one ParseChunkFile job processes mergeFileAndImportToDatabase
                // then, all other ParseChunkFile jobs do not process this action

                try {
                    $this->redis->set($importHistoryLockKey, 1);

                    $this->mergeFileAndImportToDatabase($importHistory, $hasGroup, $params);
                } catch (\Exception $e) {
                    $this->logger->warning(sprintf('mergeFileAndImportToDatabase got exception: %s %s', $e->getMessage(), $e->getTraceAsString()));
                }

                $this->loadingStatusChecker->postFileLoadingCompletedForConnectedDatSource($connectedDataSource, $concurrentLoadingFilesCountRedisKey);
                $this->loadingStatusChecker->postFileLoadingCompletedForDataSet($connectedDataSource->getDataSet());
                
                // Remove total chunk key in redis regardless merge file successfully or not
                $this->redis->del($params->getRequiredParam(self::TOTAL_CHUNK_KEY));

                // release above lock
                // then, all other ParseChunkFile jobs do nothing after
                $this->redis->del($importHistoryLockKey);
            }
        }
    }

    /**
     * handle ParseChunkFile Failed
     *
     * @param JobParams $params
     * @throws \Pubvantage\Worker\Exception\MissingJobParamException
     */
    private function handleParseChunkFileFailed(JobParams $params)
    {
        $importHistoryId = (int)$params->getRequiredParam(self::IMPORT_HISTORY_ID);
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $concurrentLoadingFilesCountRedisKey = $params->getRequiredParam(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY);

        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);

        // decrease total chunk in redis
        if ($this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY)) == 0) {
            // all chunk parsed

            $importHistoryLockKey = sprintf(ParseChunkFile::FLAG_IMPORT_COMPLETED_CHUNKS, $importHistoryId);

            if (!$this->redis->exists($importHistoryLockKey)) {
                // Set lock to make sure one ParseChunkFile job processes mergeFileAndImportToDatabase
                // then, all other ParseChunkFile jobs do not process this action
                $this->redis->set($importHistoryLockKey, 1);

                if ($connectedDataSource instanceof ConnectedDataSourceInterface) {
                    $this->loadingStatusChecker->postFileLoadingCompletedForConnectedDatSource($connectedDataSource, $concurrentLoadingFilesCountRedisKey);
                    $this->loadingStatusChecker->postFileLoadingCompletedForDataSet($connectedDataSource->getDataSet());
                }

                // Remove total chunk key in redis regardless merge file successfully or not
                $this->redis->del($params->getRequiredParam(self::TOTAL_CHUNK_KEY));

                // release above lock
                // then, all other ParseChunkFile jobs do nothing after
                $this->redis->del($importHistoryLockKey);
            }
        }
    }

    /**
     * Get directory name  of chunks file
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    private function getMergedFileDirectory(DataSourceEntryInterface $dataSourceEntry)
    {
        $chunks = $dataSourceEntry->getChunks();

        $this->logger->info(sprintf('Chunk path of merged file: %s', $chunks[0]));
        $this->logger->info(sprintf('Upload file dir: %s', $this->uploadFileDirectory));
        $firstSourceFileFullPath = sprintf('%s%s', $this->uploadFileDirectory, $chunks[0]);

        $this->logger->info(sprintf('Full path of merged file: %s', $firstSourceFileFullPath));
        return pathinfo($firstSourceFileFullPath, PATHINFO_DIRNAME);
    }

    private function removeFileOrFolder($path)
    {
        if (!is_file($path) && !is_dir($path)) {
            return;
        }

        $fs = new Filesystem();

        try {
            $fs->remove($path);
        } catch (\Exception $e) {
            $this->logger->notice($e);
        }
    }

    /**
     * @param $chunks
     * @param $mergedFile
     */
    private function deleteTemporaryFiles($chunks, $mergedFile)
    {
        $chunks = is_array($chunks) ? $chunks : [$chunks];
        $mergedFile = is_array($mergedFile) ? $mergedFile : [$mergedFile];
        $tempFiles = array_merge($chunks, $mergedFile);

        // Delete temp file
        $this->logger->notice('Delete temp file');

        try {
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    $this->removeFileOrFolder($tempFile);
                }
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * @param ImportHistoryInterface $importHistory
     * @param $hasGroup
     * @param JobParams $params
     * @throws \Pubvantage\Worker\Exception\MissingJobParamException
     */
    private function mergeFileAndImportToDatabase(ImportHistoryInterface $importHistory, $hasGroup, JobParams $params)
    {
        $dataSourceEntry = $importHistory->getDataSourceEntry();
        $connectedDataSource = $importHistory->getConnectedDataSource();

        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
        $alert = null;
        $chunks = null;
        $mergedFile = null;
        $isImportFail = false;

        if ($hasGroup) {
            try {
                $this->logger->notice('All chunks parsed completely, merging result');
                $chunks = $this->redis->lRange($params->getRequiredParam(self::CHUNKS_KEY), 0, -1);

                $mergeFileDirectory = $this->getMergedFileDirectory($dataSourceEntry);
                $mergedFileObj = new MergeFiles($chunks, $mergeFileDirectory, $importHistory->getId());
                $mergedFile = $mergedFileObj->mergeFiles();

                $this->autoImportData->parseFileOnPostGroups($connectedDataSource, $dataSourceEntry, $importHistory, $mergedFile);

                $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, null);
            } catch (ImportDataException $ex) {
                $isImportFail = true;
                $this->logger->error($ex->getMessage(), $ex->getTrace());
                $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);
            } catch (\Exception $ex) {
                $isImportFail = true;
                $this->logger->error($ex->getMessage(), $ex->getTrace());
                $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);
            }
        }

        try {
            $this->importHistoryManager->deleteOldImportHistories($importHistory);
        } catch (\Exception $e) {

        }

        $this->deleteTemporaryFiles($chunks, $mergedFile);

        $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

        $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntry->getId()));
        if ($alert instanceof ConnectedDataSourceAlertInterface && $alertProcessed == false) {
            $this->manager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
        }

        if ($isImportFail) {
            $this->logger->notice('----------------------------LOADING LARGE FILE FAILED-------------------------------------------------------------');
        } else {
            $this->logger->notice('----------------------------LOADING LARGE FILE COMPLETED-------------------------------------------------------------');
        }
    }

    /**
     * @param ImportHistoryInterface $importHistory
     * @param \Exception $ex
     * @param JobParams $params
     * @param $chunkFailedKey
     * @throws \Pubvantage\Worker\Exception\MissingJobParamException
     */
    private function handleException(ImportHistoryInterface $importHistory, \Exception $ex, JobParams $params, $chunkFailedKey)
    {
        $connectedDataSource = $importHistory->getConnectedDataSource();
        $dataSourceEntry = $importHistory->getDataSourceEntry();

        $this->logger->error($ex->getMessage(), $ex->getTrace());
        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
        $failureAlert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);

        $this->redis->set($chunkFailedKey, 1);
        $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

        $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntry->getId()));
        if ($failureAlert instanceof ConnectedDataSourceAlertInterface && $alertProcessed == false) {
            $this->manager->processAlert($failureAlert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails(), $failureAlert->getDataSourceId());
            $this->redis->set(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntry->getId()), 1);
        }
    }
}