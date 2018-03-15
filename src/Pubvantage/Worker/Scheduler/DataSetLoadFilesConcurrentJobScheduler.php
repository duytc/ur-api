<?php

namespace Pubvantage\Worker\Scheduler;

use UR\Worker\Job\Concurrent\LoadFilesConcurrentlyIntoDataSet;

class DataSetLoadFilesConcurrentJobScheduler extends LinearJobScheduler implements DataSetLoadFilesConcurrentJobSchedulerInterface
{
    public function addConcurrentJobTask($jobs, int $dataSetId, array $extraJobData = [], int $jobTTR = null)
    {
        if (empty($jobs)) {
            return [];
        }

        if (count(array_filter(array_keys($jobs), 'is_string')) > 0) {
            // support single job or many
            // if there is string key, assume it is a single job
            $jobs = [$jobs];
        }

        $jobTTR = $jobTTR === null ? $this->jobTTR : $jobTTR;

        $processedJobs = [];

        $linearTubeName = self::getDataSetTubeName($dataSetId);

        foreach ($jobs as $jobData) {
            if (!is_array($jobData)) {
                throw new \Exception('Job data must be an associative array');
            }

            $jobData = array_merge(
                $extraJobData,
                [
                    'linear_tube' => $linearTubeName,
                ],
                $jobData
            );

            $processedJobs[] = $jobData;

            $this->concurrentJobScheduler->addJob($jobData, $extraJobData, $jobTTR);
        }

        return $processedJobs;
    }

    public function createLockableProcessLinearJobTask(int $dataSetId, int $connectedDataSourceId, string $concurrentLoadFilesUniqueId)
    {
        // Each time we add any number of linear job, we must tell concurrent worker of new linear job
        // If a linear job is added quickly after another linear job, it may get processed by existing worker that already has lock
        // If this happens, this job is not needed but we should always send it because it avoids a race condition
        // If worker receives this job and there is no linear job to process, it quickly ends the execution

        $linearTubeName = self::getDataSetTubeName($dataSetId);
        $concurrentLoadingFilesCountRedisKey = self::getConcurrentLoadingFilesCountRedisKey($dataSetId, $connectedDataSourceId, $concurrentLoadFilesUniqueId);

        $jobData = [
            'task' => $this->processLinearJobName,
            'linear_tube' => $linearTubeName,
            'beanstalk_host' => $this->beanstalk->getConnection()->getHost(),
            LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_UNIQUE_ID => $concurrentLoadFilesUniqueId,
            LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY => $concurrentLoadingFilesCountRedisKey
        ];

        return $jobData;
    }

    public static function getDataSetTubeName($dataSetId)
    {
        return sprintf('ur-data-set-%d', $dataSetId);
    }

    public static function getConcurrentLoadingFilesCountRedisKey(int $dataSetId, int $connectedDataSourceId, string $concurrentLoadFilesUniqueId)
    {
        return sprintf('ur-data-set-%d-loading-files-count-%s-%s', $dataSetId, $connectedDataSourceId, $concurrentLoadFilesUniqueId);
    }
}