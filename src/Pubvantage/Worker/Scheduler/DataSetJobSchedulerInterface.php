<?php

namespace Pubvantage\Worker\Scheduler;

use Pubvantage\Worker\JobParams;

interface DataSetJobSchedulerInterface
{
    public function addJob($jobs, int $dataSetId, JobParams $parentJobParams = null);
}