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

    public function __construct(\Redis $redis, string $linearTubeExpireJobPrefix = 'ur:linear_tube_expire_job_')
    {
        $this->redis = $redis;

        if (strlen($linearTubeExpireJobPrefix) < 3) {
            throw new \InvalidArgumentException('$linearTubeExpireJobPrefix should be at least 3 characters');
        }

        $this->linearTubeExpireJobPrefix = $linearTubeExpireJobPrefix;
    }

    // this needs to get run via UI so it is immediate
    public function expireJobsInTube($linearTube, int $time)
    {
        $this->redis->set($this->getExpireKey($linearTube), $time);
    }

    public function isExpired(string $linearTube, int $time)
    {
        $expireTime = intval($this->redis->get($this->getExpireKey($linearTube)));
        return $time < $expireTime;
    }

    protected function getExpireKey(string $linearTube)
    {
        return $this->linearTubeExpireJobPrefix . $linearTube;
    }
}