<?php

namespace Pubvantage\Worker\Job;

use Pubvantage\Worker\JobParams;

interface LockableJobInterface extends JobInterface
{
    public function getLockKey(JobParams $params): string;
}