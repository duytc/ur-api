<?php

namespace UR\Worker;

// simple class for pooling multiple worker classes into one

class Pool
{
    protected $availableWorkers = [];

    /**
     * @param object[] $availableWorkers
     */
    public function __construct(array $availableWorkers)
    {
        $this->availableWorkers = $availableWorkers;
    }

    /**
     * @param string $task
     * @return object|false
     */
    public function findWorker($task)
    {
        if (!is_string($task)) {
            return false;
        }

        foreach($this->availableWorkers as $worker) {
            if(is_object($worker) && is_callable([$worker, $task])) {
                return $worker;
            }
        }

        return false;
    }
}