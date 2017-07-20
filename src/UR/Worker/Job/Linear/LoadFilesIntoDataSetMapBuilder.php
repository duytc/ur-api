<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;

class LoadFilesIntoDataSetMapBuilder implements SplittableJobInterface
{
    const JOB_NAME = 'loadFilesIntoDataSetMapBuilder';

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
        // do create all linear jobs for data set
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);

        $jobs = [];
        $jobs[] = [
            'task' => LoadFileIntoDataSetMapBuilderSubJob::JOB_NAME,
            LoadFileIntoDataSetMapBuilderSubJob::DATA_SET_ID => $dataSetId,
        ];

        if (count($jobs) > 0) {
            $jobs = array_merge($jobs, [
                ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
                ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ]);

            // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
            // this will save a lot of execution time
            $this->scheduler->addJob($jobs, $dataSetId, $params);
        }
    }
}