<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;

class DeleteConnectedDataSource implements SplittableJobInterface
{
    const JOB_NAME = 'deleteConnectedDataSource';

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

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    /** @var ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, ConnectedDataSourceManagerInterface $connectedDataSourceManager, LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository
    )
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do create all linear jobs for each files
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $jobs = [
            [
                'task' => RemoveDataFromConnectedDataSourceSubJob::JOB_NAME,
                self::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
            ],
            ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME],
        ];

        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSetId($dataSetId);
        if (!empty($linkedMapDataSets)) {
            // only add job if has augmentedDataSet
            $jobs[] = ['task' => UpdateAugmentedDataSetStatus::JOB_NAME];
        }

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}