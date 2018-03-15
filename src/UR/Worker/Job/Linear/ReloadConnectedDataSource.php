<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManager;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
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

    private $dataSourceEntryManager;

    /** @var LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    public function __construct(DataSetJobSchedulerInterface $scheduler,
                                LoggerInterface $logger,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager,
                                DataSetTableUtilInterface $dataSetTableUtil,
                                DataSourceEntryManager $dataSourceEntryManager,
                                LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->dataSetTableUtil = $dataSetTableUtil;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
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
            $jobs[] = [
                'task' => UpdateConnectedDataSourceReloadCompleted::JOB_NAME,
                UpdateConnectedDataSourceReloadCompleted::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
            ];

            $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSetId($dataSetId);
            if (!empty($linkedMapDataSets)) {
                // only add job if has augmentedDataSet
                $jobs[] = ['task' => UpdateAugmentedDataSetStatus::JOB_NAME];
            }

            $this->scheduler->addJob($jobs, $dataSetId, $params);
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

        // load files concurrently
        $jobs[] = [
            'task' => LoadFilesIntoDataSetLinearWithConcurrentSubJob::JOB_NAME,
            LoadFilesIntoDataSetLinearWithConcurrentSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
            LoadFilesIntoDataSetLinearWithConcurrentSubJob::ENTRY_IDS => $entryIds,
        ];

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}