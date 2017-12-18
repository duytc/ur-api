<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\LinearWithConcurrentJobInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use UR\Worker\Job\Concurrent\LoadFilesConcurrentlyIntoDataSet;
use UR\Worker\Job\Concurrent\ProcessLinearJob;

class LoadFileIntoDataSetLinearWithConcurrentSubJob implements LinearWithConcurrentJobInterface
{
    const JOB_NAME = 'loadFileIntoDataSetLinearWithConcurrentSubJob';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Redis */
    private $redis;

    public function __construct(LoggerInterface $logger, Redis $redis)
    {
        $this->logger = $logger;
        $this->redis = $redis;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $exitCode = ProcessLinearJob::WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB;

        if ($params->checkParamExist(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_REDIS_KEY)) {
            $concurrentCounter = $params->getRequiredParam(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_REDIS_KEY);

            if ($this->redis->get($concurrentCounter) == 0) {
                // remove redis key
                $this->redis->del($concurrentCounter);

                // we stop execution here and rely on the system restarting when ready
                $exitCode = ProcessLinearJob::WORKER_EXIT_CODE_SUCCESS;
            }
        }

        return $exitCode;
    }
}