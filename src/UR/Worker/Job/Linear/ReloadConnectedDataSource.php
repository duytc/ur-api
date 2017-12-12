<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManager;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;
use UR\Service\DataSet\ReloadParams;
use UR\Service\DataSet\ReloadParamsInterface;

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

    private $dataSourceEntryManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler,
                                LoggerInterface $logger,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager,
                                DataSetTableUtilInterface $dataSetTableUtil,
                                ImportHistoryManagerInterface $importHistoryManager,
                                DataSourceEntryManager $dataSourceEntryManager)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->dataSetTableUtil = $dataSetTableUtil;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
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
        $reloadType = $params->getRequiredParam(ReloadParamsInterface::RELOAD_TYPE);
        $reloadStartDate = $params->getParam(ReloadParamsInterface::RELOAD_START_DATE);
        $reloadEndDate = $params->getParam(ReloadParamsInterface::RELOAD_END_DATE);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $reloadParams = new ReloadParams($reloadType, $reloadStartDate, $reloadEndDate);
        $entryIds = $this->dataSetTableUtil->getEntriesByReloadParameter($connectedDataSource, $reloadParams);
        if (empty($entryIds)) {
            $this->logger->notice(sprintf('No entry of connected data source %d reloads', $connectedDataSourceId));
            return;
        }

        if ($reloadType === ReloadParamsInterface::ALL_DATA_TYPE) {
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
        }

        $jobs = [];
        foreach ($entryIds as $entryId) {
            $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
            $this->importHistoryManager->deleteImportHistoryByConnectedDataSourceAndEntry($connectedDataSource, $dataSourceEntry);
        }

        // load files concurrently
        $jobs[] = [
            'task' => LoadFilesIntoDataSetLinearWithConcurrentSubJob::JOB_NAME,
            LoadFilesIntoDataSetLinearWithConcurrentSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
            LoadFilesIntoDataSetLinearWithConcurrentSubJob::ENTRY_IDS => $entryIds,
        ];

        // also update data set total row, after each entry done, to let UI does not make user confused
        // i.e: last time, pending jobs changes from 90->60 but total rows still 0 in UI and only updated after all jobs are done
//        $jobs[] = ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME]; // TODO: use big job...

        // also update connected data source total row similar above
//        $jobs[] = ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]; // TODO: use big job...

        // TODO: use big job...
//        if (count($jobs) > 0) {
//            $jobs = array_merge($jobs, [
//                ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
//                //['task' => UpdateDataSetTotalRowSubJob::JOB_NAME], // already update after each entry done!
//                //['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME] // already update after each entry done!
//            ]);
//
//            // need update again because after overwriting total rows change
//            $jobs[] = ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME];
//            $jobs[] = ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME];
//        }

        // update connected data source that it is reload completed
        $jobs[] = [
            'task' => UpdateConnectedDataSourceReloadCompleted::JOB_NAME,
            UpdateConnectedDataSourceReloadCompleted::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ];

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}