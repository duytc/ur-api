<?php

namespace Pubvantage\Worker\Scheduler;

use DateTime;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;

class ConcurrentJobScheduler implements ConcurrentJobSchedulerInterface
{
    /**
     * @var PheanstalkProxy
     */
    private $beanstalk;
    /**
     * @var string
     */
    private $concurrentTubeName;
    /**
     * @var int
     */
    private $jobTTR;

    public function __construct(PheanstalkProxy $beanstalk, string $concurrentTubeName, int $jobTTR = 3600)
    {
        $this->beanstalk = $beanstalk;
        $this->concurrentTubeName = $concurrentTubeName;
        $this->jobTTR = $jobTTR;
    }

    public function addJob(array $jobs, array $extraJobData = [], int $jobTTR = null)
    {
        if (empty($jobs)) {
            return;
        }

        if (count(array_filter(array_keys($jobs), 'is_string')) > 0) {
            // support single job or many
            // if there is string key, assume it is a single job
            $jobs = [$jobs];
        }

        $jobTTR = $jobTTR === null ? $this->jobTTR : $jobTTR;

        foreach ($jobs as $jobData) {
            if (!is_array($jobData)) {
                throw new \Exception('Job data must be an associative array');
            }

            $jobData = array_merge(
                $extraJobData,
                [
                    'date' => (new DateTime('now'))->format(DATE_ISO8601)
                ],
                $jobData
            );

            // priority with unique incrementing job id to ensure order
            $this->beanstalk->putInTube(
                $this->concurrentTubeName,
                json_encode($jobData),
                PheanstalkProxy::DEFAULT_PRIORITY,
                PheanstalkProxy::DEFAULT_DELAY,
                $jobTTR
            );
        }
    }
}