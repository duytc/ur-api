<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\LinearWithConcurrentJobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Worker\Job\Concurrent\LoadFilesConcurrentlyIntoDataSet;

class LoadFilesIntoDataSetLinearWithConcurrentSubJob implements LinearWithConcurrentJobInterface
{
    const JOB_NAME = 'loadFilesIntoDataSetLinearWithConcurrentSubJob';

    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const DATA_SET_ID = 'data_set_id';
    const ENTRY_IDS = 'entry_ids';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var DataSetLoadFilesConcurrentJobSchedulerInterface
     */
    protected $dataSetLoadFilesConcurrentJobScheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var JobCounterInterface */
    private $jobCounter;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    public function __construct(
        DataSetJobSchedulerInterface $scheduler,
        DataSetLoadFilesConcurrentJobSchedulerInterface $dataSetLoadFilesConcurrentJobScheduler,
        LoggerInterface $logger,
        JobCounterInterface $jobCounter,
        DataSourceEntryManagerInterface $dataSourceEntryManager,
        ConnectedDataSourceManagerInterface $connectedDataSourceManager,
        ImportHistoryManagerInterface $importHistoryManager
    )
    {
        $this->scheduler = $scheduler;
        $this->dataSetLoadFilesConcurrentJobScheduler = $dataSetLoadFilesConcurrentJobScheduler;

        $this->logger = $logger;
        $this->jobCounter = $jobCounter;

        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->importHistoryManager = $importHistoryManager;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        /* get params from job params */
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $entryIds = $params->getRequiredParam(self::ENTRY_IDS);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        /* validate params */
        if (!is_array($entryIds)) {
            return;
        }

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        /* write redis key for $concurrent loading files count */
        $concurrentLoadFilesUniqueId = LoadFilesConcurrentlyIntoDataSet::createUniqueId();
        $concurrentJobsCount = count($entryIds);
        $concurrentLoadingFilesCount = DataSetLoadFilesConcurrentJobScheduler::getConcurrentLoadingFilesCountRedisKey($dataSetId, $connectedDataSourceId, $concurrentLoadFilesUniqueId);
        $this->setConcurrentLoadingFilesCountInRedis($concurrentLoadingFilesCount, $concurrentJobsCount);

        /* create linear jobs for data set linear tube, concurrent loading file jobs and process linear jobs for concurrent tube */
        // linear jobs
        $jobs = [];
        // concurrent jobs
        $concurrentJobs = [];
        // process linear jobs
        $lockableProcessLinearJobs = [];

        foreach ($entryIds as $entryId) {
            $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                continue;
            }

            $importHistoryEntity = $this->importHistoryManager->createImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);
            $importHistoryId = $importHistoryEntity->getId();
            
            // now do load files concurrently: create LoadFilesConcurrentlyIntoDataSet for concurrent tube
            $concurrentJobs[] = [
                'task' => LoadFilesConcurrentlyIntoDataSet::JOB_NAME,
                LoadFilesConcurrentlyIntoDataSet::ENTRY_ID => $entryId,
                LoadFilesConcurrentlyIntoDataSet::IMPORT_HISTORY_ID => $importHistoryId,
                LoadFilesConcurrentlyIntoDataSet::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId,
                LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_UNIQUE_ID => $concurrentLoadFilesUniqueId,
                LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY => $concurrentLoadingFilesCount
            ];
        }

        /*
         * create Lockable ProcessLinearJob for concurrent tube for all entries on ur-api-worker (main tube)
         * this process linear job takes worker to process data set linear tube,
         * then the job "LoadFilesIntoDataSetLinearWithConcurrentSubJob" will be delete if concurrentLoadingFilesCount turn to "0"
         * if not, process linear will be release back to concurrent tube
         * important: process linear job must lock together to make sure one worker process one data set linear tube at a time (this is current logic we use)
         */
        $lockableProcessLinearJobs[] = $this->dataSetLoadFilesConcurrentJobScheduler->createLockableProcessLinearJobTask($dataSetId, $connectedDataSourceId, $concurrentLoadFilesUniqueId);

        if (count($concurrentJobs) > 0) {
            $this->dataSetLoadFilesConcurrentJobScheduler->addConcurrentJobTask($concurrentJobs, $dataSetId);
        }

        $this->scheduler->addJob($jobs, $dataSetId, $params);

        // finally, put Lockable ProcessLinearJob to concurrent tube
        $this->dataSetLoadFilesConcurrentJobScheduler->addConcurrentJobTask($lockableProcessLinearJobs, $dataSetId);
    }

    private function setConcurrentLoadingFilesCountInRedis($concurrentRedisKey, $concurrentJobsCount)
    {
        $this->logger->info(sprintf('[LoadFilesIntoDataSetLinearWithConcurrentSubJob] Set pending job count for concurrent loading files count, key %s, value %d', $concurrentRedisKey, $concurrentJobsCount));
        return $this->jobCounter->setPendingJobCount($concurrentRedisKey, $concurrentJobsCount);
    }
}