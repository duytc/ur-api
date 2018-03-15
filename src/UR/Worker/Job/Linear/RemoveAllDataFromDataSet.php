<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;

class RemoveAllDataFromDataSet implements JobInterface
{
    const JOB_NAME = 'removeAllDataFromDataSet';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /** @var LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, DataSetManagerInterface $dataSetManager, LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
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
        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->error(sprintf('data set with ID (%s) not found when Remove All Data From Data Set', $dataSetId));
            return;
        }

        $jobs = [
            ['task' => TruncateDataSetSubJob::JOB_NAME],
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME],
            ['task' => UpdateDataSetReloadCompletedSubJob::JOB_NAME],
        ];

        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSetId($dataSetId);
        if (!empty($linkedMapDataSets)) {
            // only add job if has augmentedDataSet
            $jobs[] = ['task' => UpdateAugmentedDataSetStatus::JOB_NAME];
        }

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}