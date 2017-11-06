<?php

namespace Pubvantage\Worker\Scheduler;

use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;

class DataSourceEntryJobScheduler implements DataSourceEntryJobSchedulerInterface
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

    public function addJob($jobs, int $dataSourceEntryId, JobParams $parentJobParams = null)
    {
        $linearTubeName = self::getDataSetTubeName($dataSourceEntryId);

        $extraData = [
            'data_set_id' => $dataSourceEntryId,
            'linear_tube' => $linearTubeName
        ];

        $processedJobs = $this->linearJobScheduler->addJob($jobs, $linearTubeName, $extraData, $parentJobParams);
        $numJobs = count($processedJobs);

        for ($i = 0; $i < $numJobs; $i++) {
            $this->jobCounter->countPendingJob($linearTubeName);
        }
    }

    public static function getDataSetTubeName($dataSourceEntryId)
    {
        return sprintf('ur-data-source-entry-%d', $dataSourceEntryId);
    }
}