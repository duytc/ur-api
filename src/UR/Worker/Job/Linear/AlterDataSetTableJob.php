<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\Service\Report\ReportViewUpdaterInterface;

class AlterDataSetTableJob implements SplittableJobInterface
{
    const JOB_NAME = 'alterDataSetTableJob';

    const DATA_SET_ID = 'data_set_id';

    const NEW_FIELDS = 'new_fields';
    const UPDATE_FIELDS = 'update_fields';
    const DELETED_FIELDS = 'deleted_fields';

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

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);
        $newColumns = $params->getRequiredParam(self::NEW_FIELDS);
        $updateColumns = $params->getRequiredParam(self::UPDATE_FIELDS);
        $deletedColumns = $params->getRequiredParam(self::DELETED_FIELDS);

        $jobs[] = array (
            'task' => AlterDataSetTableSubJob::JOB_NAME,
            AlterDataSetTableSubJob::DATA_SET_ID => $dataSetId,
            AlterDataSetTableSubJob::NEW_FIELDS => $newColumns,
            AlterDataSetTableSubJob::UPDATE_FIELDS => $updateColumns,
            AlterDataSetTableSubJob::DELETED_FIELDS => $deletedColumns,
        );

        $jobs[] = array (
            'task' => UpdateMapDataSetWhenAlterDataSetSubJob::JOB_NAME,
            UpdateMapDataSetWhenAlterDataSetSubJob::DATA_SET_ID => $dataSetId,
            UpdateMapDataSetWhenAlterDataSetSubJob::NEW_FIELDS => $newColumns,
            UpdateMapDataSetWhenAlterDataSetSubJob::UPDATE_FIELDS => $updateColumns,
            UpdateMapDataSetWhenAlterDataSetSubJob::DELETED_FIELDS => $deletedColumns,
        );

        $jobs[] = array (
            'task' => UpdateReportViewWhenAlterDataSetSubJob::JOB_NAME,
            UpdateReportViewWhenAlterDataSetSubJob::DATA_SET_ID => $dataSetId,
            ReportViewUpdaterInterface::NEW_FIELDS => $newColumns,
            ReportViewUpdaterInterface::UPDATE_FIELDS => $updateColumns,
            ReportViewUpdaterInterface::DELETED_FIELDS => $deletedColumns,
        );

        $jobs[] = array (
            'task' => RefreshAddConditionalTransformValueWhenAlterDataSetSubJob::JOB_NAME
        );

        // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
        // this will save a lot of execution time
        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}