<?php

namespace Pubvantage\Worker\Scheduler;

use DateTime;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;

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
        $numJobs = count($processedJobs);

        for ($i = 0; $i < $numJobs; $i++) {
            $this->jobCounter->countPendingJob($linearTubeName);
        }
    }

    public static function getDataSetTubeName($dataSetId)
    {
        return sprintf('ur-data-set-%d', $dataSetId);
    }
}