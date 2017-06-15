<?php

namespace Pubvantage\Worker;

interface JobCounterInterface
{
    public function countPendingJob(string $key);

    public function getPendingJobCount(string $key): int;

    public function decrementPendingJobCount(string $key);
}