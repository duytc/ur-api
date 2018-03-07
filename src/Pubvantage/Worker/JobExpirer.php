<?php

namespace Pubvantage\Worker;

class JobExpirer implements JobExpirerInterface
{
    /**
     * @var \Redis
     */
    private $redis;
    /**
     * @var string
     */
    private $linearTubeExpireJobPrefix;

    private $jobTTR;

    public function __construct(\Redis $redis, string $linearTubeExpireJobPrefix = 'ur:linear_tube_expire_job_', int $jobTTR = 3600)
    {
        $this->redis = $redis;

        if (strlen($linearTubeExpireJobPrefix) < 3) {
            throw new \InvalidArgumentException('$linearTubeExpireJobPrefix should be at least 3 characters');
        }

        $this->linearTubeExpireJobPrefix = $linearTubeExpireJobPrefix;
        $this->jobTTR = $jobTTR;
    }

    // this needs to get run via UI so it is immediate
    public function expireJobsInTube($linearTube, int $time)
    {
        $this->redis->set($this->getExpireKey($linearTube), $time);
    }

    public function isExpired(string $linearTube, int $time)
    {
        $expireTime = intval($this->redis->get($this->getExpireKey($linearTube)));
        return $time + $this->jobTTR < $expireTime;
    }

    protected function getExpireKey(string $linearTube)
    {
        return $this->linearTubeExpireJobPrefix . $linearTube;
    }
}