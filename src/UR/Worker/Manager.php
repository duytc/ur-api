<?php

namespace UR\Worker;

use Pheanstalk_PheanstalkInterface;
use StdClass;
use UR\Service\DateUtilInterface;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;

// responsible for creating background tasks

class Manager
{
    const TUBE = 'ur-api-worker';
    const EXECUTION_TIME_THRESHOLD = 3600;

    protected $dateUtil;

    /**
     * @var PheanstalkProxyInterface
     */
    protected $queue;

    public function __construct(DateUtilInterface $dateUtil, PheanstalkProxyInterface $queue)
    {
        $this->dateUtil = $dateUtil;
        $this->queue = $queue;
    }

    public function reImportWhenDataSetChange($entryIds){
        $params = new StdClass;
        $params->entryIds = $entryIds;
        $this->queueTask('reImportWhenDataSetChange', $params);
    }

    public function importDataWhenConnectedDataSourceChange($entryIds){
        $params = new StdClass;
        $params->entryIds = $entryIds;
        $this->queueTask('importDataWhenConnectedDataSourceChange', $params);
    }

    public function processAlert($parameters){
        $params = new StdClass;
        $params->parameters = $parameters;
        $this->queueTask('processAlert', $params);
    }

    /**
     * @param string $task
     * @param StdClass $params
     */
    protected function queueTask($task, StdClass $params)
    {
        $payload = new StdClass;

        $payload->task = $task;
        $payload->params = $params;

        $this->queue
            ->useTube(static::TUBE)
            ->put(json_encode($payload),
                Pheanstalk_PheanstalkInterface::DEFAULT_PRIORITY,
                Pheanstalk_PheanstalkInterface::DEFAULT_DELAY,
                self::EXECUTION_TIME_THRESHOLD)
        ;
    }
}