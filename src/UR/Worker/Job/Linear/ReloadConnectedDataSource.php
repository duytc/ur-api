<?php

namespace UR\Worker\Job\Linear;

use DateTime;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;

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

    /**
     * @var DataSetTableUtilInterface
     */
    private $dataSetTableUtil;

    private $importHistoryManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager,
                                DataSetTableUtilInterface $dataSetTableUtil, ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->dataSetTableUtil = $dataSetTableUtil;
        $this->importHistoryManager = $importHistoryManager;
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

        $entryIds = $this->dataSetTableUtil->getEntriesByDateRange($connectedDataSource, $connectedDataSource->getReloadStartDate(), $connectedDataSource->getReloadEndDate());
        if (empty($entryIds)) {
            $this->logger->notice(sprintf('No entry of connected data source %d reloads', $connectedDataSourceId));
            return;
        }
        // remove data first
        $this->scheduler->addJob([
            [
                'task' => RemoveDataFromConnectedDataSourceSubJob::JOB_NAME,
                RemoveDataFromConnectedDataSourceSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
            ],

            // also update data set total row, after each entry done, to let UI does not make user confused
            // i.e: last time, pending jobs changes from 90->60 but total rows still 0 in UI and only updated after all jobs are done
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],

            // also update connected data source total row similar above
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
        ], $dataSetId, $params);

        $jobs = [];
        foreach ($entryIds as $entryId) {
            $this->importHistoryManager->deleteImportHistoryByConnectedDataSourceAndEntry($connectedDataSourceId, $entryId);
            $jobs[] = [
                'task' => LoadFileIntoDataSetSubJob::JOB_NAME,
                LoadFileIntoDataSetSubJob::ENTRY_ID => $entryId,
                self::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
            ];
        }

        if (count($jobs) > 0) {
            $jobs = array_merge($jobs, [
                ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
                //['task' => UpdateDataSetTotalRowSubJob::JOB_NAME], // already update after each entry done!
                //['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME] // already update after each entry done!
            ]);

            // need update again because after overwriting total rows change
            $jobs[] = ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME];
            $jobs[] = ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME];
        }

        // update connected data source that it is reload completed
        $jobs[] = [
            'task' => UpdateConnectedDataSourceReloadCompleted::JOB_NAME,
            UpdateConnectedDataSourceReloadCompleted::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ];

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}