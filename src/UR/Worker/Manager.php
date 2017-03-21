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

    public function loadingDataFromFileToDataImportTable($connectedDataSourceId, $entryId, $dataSetId)
    {
        $params = new StdClass;
        $params->connectedDataSourceId = $connectedDataSourceId;
        $params->entryId = $entryId;
        $params->dataSetId = $dataSetId;

        $this->queueTask('loadingDataFromFileToDataImportTable', $params);
    }

    public function processAlert($code, $publisherId, $details)
    {
        $params = new StdClass;
        $params->code = $code;
        $params->publisherId = $publisherId;
        $params->details = $details;

        $this->queueTask('processAlert', $params);
    }

    public function alterDataSetTable($dataSetId, $newFields, $updateFields, $deletedFields)
    {
        $params = new StdClass;
        $params->dataSetId = $dataSetId;
        $params->newColumns = $newFields;
        $params->updateColumns = $updateFields;
        $params->deletedColumns = $deletedFields;

        $this->queueTask('alterDataSetTable', $params);
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
                self::EXECUTION_TIME_THRESHOLD);
    }
}