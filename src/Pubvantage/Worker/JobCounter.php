<?php

namespace Pubvantage\Worker;

use Redis;

class JobCounter implements JobCounterInterface
{
    /**
     * @var Redis
     */
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

    public function increasePendingJob(string $key)
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
        $counterKey = $this->getCountKey($key);
        if ($this->redis->exists($counterKey)) {
            $this->redis->decr($counterKey);
        }
    }

    public function setPendingJobCount(string $key, int $count)
    {
        $this->redis->set($this->getCountKey($key), $count);
    }

    public function delPendingJobCount(string $key)
    {
        $counterKey = $this->getCountKey($key);
        if ($this->redis->exists($counterKey)) {
            $this->redis->del($counterKey);
        }
    }

    protected function getCountKey(string $key): string
    {
        return $this->pendingJobCountKeyPrefix . $key;
    }
}