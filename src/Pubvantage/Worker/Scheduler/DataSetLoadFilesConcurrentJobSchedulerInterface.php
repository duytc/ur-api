<?php

namespace Pubvantage\Worker\Scheduler;

interface DataSetLoadFilesConcurrentJobSchedulerInterface
{
    public function addConcurrentJobTask($jobs, int $dataSetId, array $extraJobData = [], int $jobTTR = null);

    public function createLockableProcessLinearJobTask(int $dataSetId, string $uniqueId, string $loadFilesUniqueId);
}