<?php

namespace UR\Service\Import;


use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Worker\Job\Linear\UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob;
use UR\Worker\Job\Linear\UpdateAugmentedDataSetStatus;
use UR\Worker\Job\Linear\UpdateConnectedDataSourceReloadCompleted;
use UR\Worker\Job\Linear\UpdateDataSetReloadCompleted;
use UR\Worker\Job\Linear\UpdateDataSetTotalRowSubJob;
use UR\Worker\Job\Linear\UpdateOverwriteDateInDataSetSubJob;

class LoadingStatusChecker implements LoadingStatusCheckerInterface
{
    /** @var JobCounterInterface */
    private $jobCounter;

    /** @var DataSetJobSchedulerInterface */
    private $scheduler;

    function __construct(JobCounterInterface $jobCounter, DataSetJobSchedulerInterface $scheduler)
    {
        $this->jobCounter = $jobCounter;
        $this->scheduler = $scheduler;
    }

    /**
     * @param DataSetInterface $dataSet
     */
    public function postFileLoadingCompletedForDataSet(DataSetInterface $dataSet)
    {
        $dataSetId = $dataSet->getId();
        $linearTubeName = DataSetLoadFilesConcurrentJobScheduler::getDataSetTubeName($dataSetId);

        $this->jobCounter->decrementPendingJobCount($linearTubeName);
        $pendingJob = $this->jobCounter->getPendingJobCount($linearTubeName);
        
        if ($pendingJob < 1) {
            $jobs[] = ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME];
            $jobs[] = ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME];
            $jobs[] = ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME];
            $jobs[] = ['task' => UpdateDataSetReloadCompleted::JOB_NAME];
            $jobs[] = ['task' => UpdateAugmentedDataSetStatus::JOB_NAME];
            $this->scheduler->addJob($jobs, $dataSetId);
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $concurrentLoadingFilesCountRedisKey
     */
    public function postFileLoadingCompletedForConnectedDatSource(ConnectedDataSourceInterface $connectedDataSource, $concurrentLoadingFilesCountRedisKey)
    {
        $this->jobCounter->decrementPendingJobCount($concurrentLoadingFilesCountRedisKey);
        $pendingJob = $this->jobCounter->getPendingJobCount($concurrentLoadingFilesCountRedisKey);

        if ($pendingJob < 1) {
            $jobs[] = [
                'task' => UpdateConnectedDataSourceReloadCompleted::JOB_NAME,
                UpdateConnectedDataSourceReloadCompleted::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId()
            ];

            $this->scheduler->addJob($jobs, $connectedDataSource->getDataSet()->getId());
        }
    }
}