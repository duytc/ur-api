<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;

class UpdateAugmentedDataSetStatus implements JobInterface
{
    const JOB_NAME = 'UpdateAugmentedDataSetStatus';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger)
    {
        $this->scheduler = $scheduler;

        $this->logger = $logger;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);

        $jobs[] = [
            'task' => UpdateAugmentedDataSetStatusSubJob::JOB_NAME
        ];

        // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
        // this will save a lot of execution time
        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}