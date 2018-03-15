<?php

namespace Pubvantage\Worker\Scheduler;

use DateTime;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Pubvantage\Worker\JobExpirerInterface;
use Pubvantage\Worker\JobParams;
use Redis;

class LinearJobScheduler implements LinearJobSchedulerInterface
{
    /**
     * @var PheanstalkProxy
     */
    protected $beanstalk;
    /**
     * @var Redis
     */
    protected $redis;
    /**
     * @var ConcurrentJobSchedulerInterface
     */
    protected $concurrentJobScheduler;
    /**
     * @var string
     */
    protected $processLinearJobName;
    /**
     * @var string
     */
    protected $priorityKeyPrefix;
    /**
     * @var int
     */
    protected $jobTTR;

    protected $jobExpirer;

    public function __construct(
        PheanstalkProxy $beanstalk,
        Redis $redis,
        ConcurrentJobSchedulerInterface $concurrentJobScheduler,
        string $processLinearJobName,
        string $priorityKeyPrefix = 'linear_job_next_priority_tube_',
        JobExpirerInterface $jobExpirer,
        int $jobTTR = 3600
    )
    {
        $this->beanstalk = $beanstalk;
        $this->redis = $redis;
        $this->concurrentJobScheduler = $concurrentJobScheduler;
        $this->processLinearJobName = $processLinearJobName;
        $this->priorityKeyPrefix = $priorityKeyPrefix;
        $this->jobExpirer = $jobExpirer;
        $this->jobTTR = $jobTTR;
    }

    public function addJob($jobs, string $linearTubeName, array $extraJobData = [], JobParams $parentJobParams = null, int $jobTTR = null): array
    {
        if (empty($jobs)) {
            return [];
        }

        if (count(array_filter(array_keys($jobs), 'is_string')) > 0) {
            // support single job or many
            // if there is string key, assume it is a single job
            $jobs = [$jobs];
        }

        $date = ($parentJobParams instanceof JobParams) ? DateTime::createFromFormat(DATE_ISO8601, $parentJobParams->getRequiredParam('date')) : null;
        $priority = ($parentJobParams instanceof JobParams) ? (int)$parentJobParams->getRequiredParam('priority') : null;

        $jobTTR = $jobTTR === null ? $this->jobTTR : $jobTTR;

        $processedJobs = [];

        foreach ($jobs as $jobData) {
            if (!is_array($jobData)) {
                throw new \Exception('Job data must be an associative array');
            }

            $priority = ($priority === null) ? $this->getNextJobPriority($linearTubeName) + PheanstalkProxy::DEFAULT_PRIORITY : $priority;

            $date = $date === null ? new DateTime('now') : $date;

            $this->jobExpirer->expireJobsInTube($linearTubeName, $date->getTimestamp());

            $jobData = array_merge(
                $extraJobData,
                [
                    'priority' => $priority,
                    'date' => $date->format(DATE_ISO8601),
                    'timestamp' => time(),
                ],
                $jobData
            );

            $processedJobs[] = $jobData;

            // priority with unique incrementing job id to ensure order
            $this->beanstalk->putInTube(
                $linearTubeName,
                json_encode($jobData),
                $priority,
                PheanstalkProxy::DEFAULT_DELAY,
                $jobTTR
            );
        }

        $this->addProcessLinearJobTask($linearTubeName);

        return $processedJobs;
    }

    public function getNextJobPriority($linearTubeName)
    {
        return $this->redis->incr(sprintf('%s%s', $this->priorityKeyPrefix, $linearTubeName));
    }

    protected function addProcessLinearJobTask(string $linearTubeName)
    {
        // Each time we add any number of linear job, we must tell concurrent worker of new linear job
        // If a linear job is added quickly after another linear job, it may get processed by existing worker that already has lock
        // If this happens, this job is not needed but we should always send it because it avoids a race condition
        // If worker receives this job and there is no linear job to process, it quickly ends the execution

        $jobData = [
            'task' => $this->processLinearJobName,
            'linear_tube' => $linearTubeName,
            'beanstalk_host' => $this->beanstalk->getConnection()->getHost(),
            'priority' => 0,
        ];

        $this->concurrentJobScheduler->addJob($jobData);
    }
}