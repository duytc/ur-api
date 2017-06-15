<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;

class DeleteFiles implements SplittableJobInterface
{
    const JOB_NAME = 'loadFilesIntoDataSet';

    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const DATA_SET_ID = 'data_set_id';
    const ENTRY_IDS = 'entry_ids';

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
        // do create all linear jobs for each files
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $entryIds = $params->getRequiredParam(self::ENTRY_IDS);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        if (!is_array($entryIds)) {
            return;
        }

        $jobs = [];

        foreach ($entryIds as $entryId) {
            $jobs[] = [
                'task' => LoadFileIntoDataSetSubJob::JOB_NAME,
                LoadFileIntoDataSetSubJob::ENTRY_ID => $entryId,
                LoadFileIntoDataSetSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
            ];
        }

        if (count($jobs) > 0) {
            $this->scheduler->addJob($jobs, $dataSetId, $params);

            // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
            // this will save a lot of execution time
            $this->scheduler->addJob([
                'task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME
            ], $dataSetId, $params);

            $this->scheduler->addJob([
                'task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME
            ], $dataSetId, $params);

            $this->scheduler->addJob([
                'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
            ], $dataSetId, $params);
        }
    }
}