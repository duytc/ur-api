<?php

namespace Pubvantage\Worker\Scheduler;

use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;
use UR\Worker\Job\Linear\LoadFileIntoDataSetSubJob;

class DataSetJobScheduler implements DataSetJobSchedulerInterface
{
    /**
     * @var LinearJobSchedulerInterface
     */
    private $linearJobScheduler;
    /**
     * @var JobCounterInterface
     */
    private $jobCounter;

    public function __construct(LinearJobSchedulerInterface $linearJobScheduler, JobCounterInterface $jobCounter)
    {
        $this->linearJobScheduler = $linearJobScheduler;
        $this->jobCounter = $jobCounter;
    }

    public function addJob($jobs, int $dataSetId, JobParams $parentJobParams = null)
    {
        $linearTubeName = self::getDataSetTubeName($dataSetId);

        $extraData = [
            'data_set_id' => $dataSetId,
            'linear_tube' => $linearTubeName
        ];

        $processedJobs = $this->linearJobScheduler->addJob($jobs, $linearTubeName, $extraData, $parentJobParams);

        $jobs = is_array($jobs) ? $jobs : [$jobs];

        foreach ($jobs as $job) {
            if (!is_array($job) || !array_key_exists('task', $job) || !array_key_exists(LoadFileIntoDataSetSubJob::ENTRY_ID, $job)) {
                continue;
            }

            $entryId = $job[LoadFileIntoDataSetSubJob::ENTRY_ID];

            if (!empty($entryId)) {
                $this->jobCounter->increasePendingJob($linearTubeName);
            }
        }
    }

    public static function getDataSetTubeName($dataSetId)
    {
        return sprintf('ur-data-set-%d', $dataSetId);
    }
}