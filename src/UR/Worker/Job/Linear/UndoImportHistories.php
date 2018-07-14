<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;

class UndoImportHistories implements SplittableJobInterface
{
    const JOB_NAME = 'undoImportHistories';

    const DATA_SET_ID = 'data_set_id';
    const IMPORT_HISTORY_IDS = 'import_history_ids';

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
        $importHistoryIds = $params->getRequiredParam(self::IMPORT_HISTORY_IDS);

        if (!is_array($importHistoryIds)) {
            return;
        }

        $jobs = [];
        $jobs[] = [
            'task' => UndoImportHistorySubJob::JOB_NAME,
            UndoImportHistorySubJob::IMPORT_HISTORY_IDS => $importHistoryIds
        ];

        $jobs = array_merge($jobs, [
            ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME],
            ['task' => UpdateAugmentedDataSetStatus::JOB_NAME],
            ['task' => CreateAlertOnAugmentedDataSetChangedJob::JOB_NAME],
        ]);

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}