<?php

namespace Pubvantage\Worker\Scheduler;

use Pubvantage\Worker\JobParams;

interface LinearJobSchedulerInterface
{
    public function addJob($jobs, string $linearTubeName, array $extraJobData = [], JobParams $parentJobParams = null, int $jobTTR = null): array;

    public function getNextJobPriority($dataSetId);
}