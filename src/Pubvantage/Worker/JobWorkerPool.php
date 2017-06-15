<?php

namespace Pubvantage\Worker;

use Pubvantage\Worker\Job\JobInterface;

class JobWorkerPool
{
    protected $jobs = [];

    public function __construct(array $jobs)
    {
        foreach($jobs as $job) {
            $this->addJob($job);
        }
    }

    public function addJob(JobInterface $job)
    {
        if (isset($this->jobs[$job->getName()])) {
            throw new \Exception(sprintf('job "%s" already exists in pool', $job->getName()));
        }

        $this->jobs[$job->getName()] = $job;
    }

    /**
     * @param string $task
     * @return bool|JobInterface
     */
    public function findJob(string $task)
    {
        if (isset($this->jobs[$task])) {
            return $this->jobs[$task];
        }

        return false;
    }
}