<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;

class ReloadConnectedDataSource implements SplittableJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'reloadConnectedDataSource';

    const DATA_SET_ID = 'data_set_id';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $connectedDataSourceManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, ConnectedDataSourceManagerInterface $connectedDataSourceManager)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
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
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        // remove data first
        $this->scheduler->addJob([
            'task' => RemoveDataFromConnectedDataSourceSubJob::JOB_NAME,
            RemoveDataFromConnectedDataSourceSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ], $dataSetId, $params);

        $entries = $connectedDataSource->getDataSource()->getDataSourceEntries();
        $entryIds = array_map(function (DataSourceEntryInterface $entry) {
            return $entry->getId();
        }, $entries->toArray());

        $jobs = [];

        foreach ($entryIds as $entryId) {
            $jobs[] = [
                'task' => LoadFileIntoDataSetSubJob::JOB_NAME,
                LoadFileIntoDataSetSubJob::ENTRY_ID => $entryId,
                self::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
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
                'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
            ], $dataSetId, $params);

            $this->scheduler->addJob([
                'task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME
            ], $dataSetId, $params);
        }
    }
}