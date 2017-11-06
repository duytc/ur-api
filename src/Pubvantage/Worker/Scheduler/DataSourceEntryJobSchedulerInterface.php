<?php

namespace Pubvantage\Worker\Scheduler;

use Pubvantage\Worker\JobParams;

interface DataSourceEntryJobSchedulerInterface
{
    public function addJob($jobs, int $dataSourceEntryId, JobParams $parentJobParams = null);
}