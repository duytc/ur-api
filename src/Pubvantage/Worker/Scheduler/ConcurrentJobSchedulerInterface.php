<?php

namespace Pubvantage\Worker\Scheduler;

interface ConcurrentJobSchedulerInterface
{
    public function addJob(array $jobs, array $extraJobData = [], int $jobTTR = null);
}