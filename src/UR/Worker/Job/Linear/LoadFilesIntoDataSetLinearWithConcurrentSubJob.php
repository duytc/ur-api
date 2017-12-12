<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\LinearWithConcurrentJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobSchedulerInterface;
use Redis;
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

    /** @var Redis */
    private $redis;

    public function __construct(DataSetJobSchedulerInterface $scheduler, DataSetLoadFilesConcurrentJobSchedulerInterface $dataSetLoadFilesConcurrentJobScheduler, LoggerInterface $logger, Redis $redis)
    {
        $this->scheduler = $scheduler;
        $this->dataSetLoadFilesConcurrentJobScheduler = $dataSetLoadFilesConcurrentJobScheduler;

        $this->logger = $logger;
        $this->redis = $redis;
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
        // do create all linear jobs for each files
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $entryIds = $params->getRequiredParam(self::ENTRY_IDS);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        if (!is_array($entryIds)) {
            return;
        }

        // write redis key for $concurrentJobsCount
        $loadFilesUniqueId = LoadFilesConcurrentlyIntoDataSet::createUniqueId();
        $concurrentJobsCount = count($entryIds);
        $concurrentRedisKey = DataSetLoadFilesConcurrentJobScheduler::getConcurrentRedisKeyForLoadingFilesCount($dataSetId, $loadFilesUniqueId);
        $this->setConcurrentJobsCountInRedis($concurrentRedisKey, $concurrentJobsCount);

        $jobs = [];
        $concurrentJobs = [];
        $lockableProcessLinearJobs = [];
        foreach ($entryIds as $entryId) {
            // load files linear (only for decrease loading file count from redis key)
            $jobs[] = [
                'task' => LoadFileIntoDataSetLinearWithConcurrentSubJob::JOB_NAME,
                LoadFilesConcurrentlyIntoDataSet::CONCURRENT_REDIS_KEY => $concurrentRedisKey
            ];

            // now do load files concurrently!
            // create LoadFilesConcurrentlyIntoDataSet for concurrent tube
            $uniqueId = LoadFilesConcurrentlyIntoDataSet::createUniqueId();
            $concurrentJobs[] = [
                'task' => LoadFilesConcurrentlyIntoDataSet::JOB_NAME,
                LoadFilesConcurrentlyIntoDataSet::ENTRY_ID => $entryId,
                LoadFilesConcurrentlyIntoDataSet::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId,
                LoadFilesConcurrentlyIntoDataSet::UNIQUE_ID => $uniqueId,
                LoadFilesConcurrentlyIntoDataSet::CONCURRENT_REDIS_KEY => $concurrentRedisKey
            ];

            // create LockableProcessLinearJob for concurrent tube
            $lockableProcessLinearJobs[] = $this->dataSetLoadFilesConcurrentJobScheduler->createLockableProcessLinearJobTask($dataSetId, $uniqueId, $loadFilesUniqueId);
        }

        // put n LoadFileIntoDataSetSubJob to linear tube
        if (count($concurrentJobs) > 0) {
            $this->dataSetLoadFilesConcurrentJobScheduler->addConcurrentJobTask($concurrentJobs, $dataSetId);
        }

        if (count($jobs) > 0) {
            $jobs = array_merge($jobs, [
                ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
                ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
                ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
            ]);

            // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
            // this will save a lot of execution time
            $this->scheduler->addJob($jobs, $dataSetId, $params);
        }

        // finally, put n LockableProcessLinearJob to concurrent tube
        $this->dataSetLoadFilesConcurrentJobScheduler->addConcurrentJobTask($lockableProcessLinearJobs, $dataSetId);
    }

    private function setConcurrentJobsCountInRedis($concurrentRedisKey, $concurrentJobsCount)
    {
        return $this->redis->set($concurrentRedisKey, $concurrentJobsCount);
    }
}