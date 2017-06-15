<?php

namespace Pubvantage\Worker\Job;

use Pubvantage\Worker\JobParams;

interface JobInterface
{
    public function getName(): string;
    public function run(JobParams $params);
}