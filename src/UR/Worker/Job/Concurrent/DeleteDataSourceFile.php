<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\ConcurrentJobSchedulerInterface;

class DeleteDataSourceFile implements JobInterface
{
    const JOB_NAME = 'deleteDataSourceFile';

    /**
     * @var ConcurrentJobSchedulerInterface
     */
    private $scheduler;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ConcurrentJobSchedulerInterface $scheduler, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->scheduler = $scheduler;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // you can schedule other concurrent jobs here such as updated detected fields

        // does not exist yet
        $this->scheduler->addJob(['task' => 'updateDataSourceDetectedFields']);

        // do something here

        usleep(100000);

        $this->logger->notice(sprintf('Executed linear job "%s" on data set %d', $this->getName(), $params->getRequiredParam('data_set_id')));
    }
}