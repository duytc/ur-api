<?php

namespace Pubvantage\Worker;

use Redis;

class JobCounter implements JobCounterInterface
{
    private $redis;
    /**
     * @var string
     */
    private $pendingJobCountKeyPrefix;

    public function __construct(Redis $redis, $pendingJobCountKeyPrefix = 'pending_job_count_')
    {
        $this->redis = $redis;
        $this->pendingJobCountKeyPrefix = $pendingJobCountKeyPrefix;
    }

    public function countPendingJob(string $key)
    {
        $this->redis->incr($this->getCountKey($key));
    }

    public function getPendingJobCount(string $key): int
    {
        $count = (int) $this->redis->get($this->getCountKey($key));

        if ($count < 0) {
            $count = 0;
        }

        return $count;
    }

    public function decrementPendingJobCount(string $key)
    {
        $this->redis->decr($this->getCountKey($key));
    }

    protected function getCountKey(string $key): string
    {
        return $this->pendingJobCountKeyPrefix . $key;
    }
}