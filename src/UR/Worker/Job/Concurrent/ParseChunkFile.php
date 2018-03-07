<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\RedLock;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobScheduler;
use Redis;
use Symfony\Component\Filesystem\Filesystem;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;
use UR\Service\DataSource\MergeFiles;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\ImportDataException;
use UR\Worker\Job\Linear\LoadFileIntoDataSetSubJob;
use UR\Worker\Manager;

class ParseChunkFile implements JobInterface
{
    const DATA_SOURCE_ENTRY_ID = 'data_source_entry_id';
    const IMPORT_HISTORY_ID = 'import_history_id';
    const JOB_NAME = 'parse_chunk_file';
    const INPUT_FILE_PATH = 'input_file_path';
    const OUTPUT_FILE_PATH = 'output_file_path';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const TOTAL_CHUNK_KEY = 'parse_chunk_file:total';
    const CHUNKS_KEY = 'parse_chunk_file:chunks';
    const PROCESS_ALERT_KEY_TEMPLATE = 'data_source_entry_%d_alert';

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
    
    /** @var JobCounterInterface */
    private $jobCounter;

    /**
     * ParseChunkFile constructor.
     * @param LoggerInterface $logger
     * @param Manager $manager
     * @param Redis $redis
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param ConnectedDataSourceManagerInterface $connectedDataSourceManager
     * @param AutoImportDataInterface $autoImportData
     * @param ImportHistoryManagerInterface $importHistoryManager
     * @param $tempFileDirectory
     * @param $uploadFileDirectory
     * @param JobCounterInterface $jobCounter
     */
    public function __construct(LoggerInterface $logger, Manager $manager, Redis $redis, DataSourceEntryManagerInterface $dataSourceEntryManager,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager, AutoImportDataInterface $autoImportData, ImportHistoryManagerInterface $importHistoryManager, $tempFileDirectory, $uploadFileDirectory, JobCounterInterface $jobCounter)
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
        $this->jobCounter = $jobCounter;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $importHistoryId = $params->getRequiredParam(self::IMPORT_HISTORY_ID);
        $chunkFailedKey = sprintf(LoadFileIntoDataSetSubJob::CHUNK_FAILED_KEY_TEMPLATE, $importHistoryId);
        $connectedDataSourceId = $params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $dataSourceEntryId = $params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->error(sprintf('ConnectedDataSource %d not found or you do not have permission', $connectedDataSourceId));
            $this->redis->set($chunkFailedKey, 1);
            return;
        }

        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            $this->redis->set($chunkFailedKey, 1);
            return;
        }


        $importHistory = $this->importHistoryManager->find($importHistoryId);
        if (!$importHistory instanceof ImportHistoryInterface) {
            $this->logger->error(sprintf('ImportHistory %d not found or you do not have permission', $importHistoryId));
            $this->redis->set($chunkFailedKey, 1);
            return;
        }

        // if one failed, all failed
        $failed = $this->redis->exists($chunkFailedKey);
        if ($failed == true) {
            //all chunk parsed
            if ($this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY)) == 0) {
                //Decrease pending job count
                $linearTubeName = DataSetJobScheduler::getDataSetTubeName($connectedDataSource->getDataSet()->getId());
                $this->jobCounter->decrementPendingJobCount($linearTubeName);
            }
            
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
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            $failureAlert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);

            $this->redis->set($chunkFailedKey, 1);
            $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

            $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId));
            if ($failureAlert instanceof ConnectedDataSourceAlertInterface && $alertProcessed == false) {
                $this->manager->processAlert($failureAlert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails(), $failureAlert->getDataSourceId());
                $this->redis->set(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId), 1);
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            $failureAlert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);

            $this->redis->set($chunkFailedKey, 1);
            $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

            $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId));
            if ($failureAlert instanceof ConnectedDataSourceAlertInterface && $alertProcessed == false) {
                $this->manager->processAlert(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR, $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails(), $failureAlert->getDataSourceId());
                $this->redis->set(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId), 1);
            }
        }

        //all chunk parsed
        if ($this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY)) == 0) {
            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            $alert = null;
            $chunks = null;
            $mergedFile = null;

            if ($hasGroup) {
                $this->logger->notice('All chunks parsed completely, merging result');
                $chunks = $this->redis->lRange($params->getRequiredParam(self::CHUNKS_KEY), 0, -1);

                try {
                    $mergeFileDirectory = $this->getMergedFileDirectory($dataSourceEntry);
                    $mergedFileObj = new MergeFiles($chunks, $mergeFileDirectory, $importHistory->getId());
                    $mergedFile = $mergedFileObj->mergeFiles();
                    $this->autoImportData->parseFileOnPostGroups($connectedDataSource, $dataSourceEntry, $importHistory, $mergedFile);
                    $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, null);
                } catch (ImportDataException $ex) {
                    $this->logger->error($ex->getMessage(), $ex->getTrace());
                    $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);
                } catch (\Exception $ex) {
                    $this->logger->error($ex->getMessage(), $ex->getTrace());
                    $alert = $connectedDataSourceAlertFactory->getAlertByException($importHistory, $ex);
                }
            }

            $this->deleteTemporaryFiles($chunks, $mergedFile);

            $dataSetId = $connectedDataSource->getDataSet()->getId();
            $this->manager->updateOverwriteDateForDataSet($dataSetId);
            $this->manager->updateTotalRowsForDataSet($dataSetId);
            $this->manager->updateAllConnectedDataSourceTotalRowForDataSet($dataSetId);

            $this->redis->del($params->getRequiredParam(self::TOTAL_CHUNK_KEY));
            $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

            $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId));
            if ($alert instanceof ConnectedDataSourceAlertInterface && $alertProcessed == false) {
                $this->manager->processAlert($alert->getAlertCode(), $connectedDataSource->getDataSource()->getPublisherId(), $alert->getDetails(), $alert->getDataSourceId());
            }
         
            //Decrease pending job count
            $linearTubeName = DataSetJobScheduler::getDataSetTubeName($dataSetId);
            $this->jobCounter->decrementPendingJobCount($linearTubeName);
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
}