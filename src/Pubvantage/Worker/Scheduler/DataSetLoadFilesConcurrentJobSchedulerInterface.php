<?php

namespace Pubvantage\Worker\Scheduler;


interface DataSetLoadFilesConcurrentJobSchedulerInterface
{
    /**
     * add concurrent job task to concurrent tube
     *
     * @param $jobs
     * @param int $dataSetId
     * @param array $extraJobData
     * @param int|null $jobTTR
     * @return mixed
     */
    public function addConcurrentJobTask($jobs, int $dataSetId, array $extraJobData = [], int $jobTTR = null);

    /**
     * create lockable process linear job task (to support concurrent loading files)
     *
     * @param int $dataSetId
     * @param int $connectedDataSourceId
     * @param string $concurrentLoadFilesUniqueId
     * @return mixed
     */
    public function createLockableProcessLinearJobTask(int $dataSetId, int $connectedDataSourceId, string $concurrentLoadFilesUniqueId);
}