<?php

namespace Pubvantage\Worker;

interface JobCounterInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function increasePendingJob(string $key);

    /**
     * @param string $key
     * @return int
     */
    public function getPendingJobCount(string $key): int;

    /**
     * @param string $key
     * @return mixed
     */
    public function decrementPendingJobCount(string $key);

    /**
     * @param string $key
     * @param int $count
     * @return mixed
     */
    public function setPendingJobCount(string $key, int $count);

    /**
     * @param string $key
     * @return mixed
     */
    public function delPendingJobCount(string $key);
}