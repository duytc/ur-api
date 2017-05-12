<?php

namespace UR\Worker;

use Pheanstalk_PheanstalkInterface;
use stdClass;
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

    /**
     * @param int $connectedDataSourceId
     * @param int $entryId
     * @param int $dataSetId
     * @param string $importJobId
     */
    public function loadingDataFromFileToDataImportTable($connectedDataSourceId, $entryId, $dataSetId, $importJobId)
    {
        $params = new stdClass;
        $params->connectedDataSourceId = $connectedDataSourceId;
        $params->entryId = $entryId;
        $params->dataSetId = $dataSetId;
        $params->importJobId = $importJobId;

        $this->queueTask('loadingDataFromFileToDataImportTable', $params);
    }

    /**
     * @param int $code
     * @param int $publisherId
     * @param array $details
     */
    public function processAlert($code, $publisherId, $details)
    {
        $params = new stdClass;
        $params->code = $code;
        $params->publisherId = $publisherId;
        $params->details = $details;

        $this->queueTask('processAlert', $params);
    }

    /**
     * @param int $dataSetId
     * @param array $newFields
     * @param array $updateFields
     * @param array $deletedFields
     * @param string $importJobId
     */
    public function alterDataSetTable($dataSetId, $newFields, $updateFields, $deletedFields, $importJobId)
    {
        $params = new stdClass;
        $params->dataSetId = $dataSetId;
        $params->newColumns = $newFields;
        $params->updateColumns = $updateFields;
        $params->deletedColumns = $deletedFields;
        $params->importJobId = $importJobId;

        $this->queueTask('alterDataSetTable', $params);
    }

    public function truncateDataSetTable($dataSetId, $importJobId)
    {
        $params = new StdClass;
        $params->dataSetId = $dataSetId;
        $params->importJobId = $importJobId;

        $this->queueTask('truncateDataSetTable', $params);
    }

    public function truncateDataSet($dataSetId)
    {
        $params = new StdClass;
        $params->id = $dataSetId;

        $this->queueTask('truncateDataSet', $params);
    }

    public function updateDetectedFieldsAndTotalRowWhenEntryInserted($entryId)
    {
        $params = new StdClass;
        $params->entryId = $entryId;

        $this->queueTask('updateDetectedFieldsAndTotalRowWhenEntryInserted', $params);
    }

    public function updateDetectedFieldsWhenEntryDeleted($format, $path, $dataSourceId)
    {
        $params = new StdClass;
        $params->format = $format;
        $params->path = $path;
        $params->dataSourceId = $dataSourceId;

        $this->queueTask('updateDetectedFieldsWhenEntryDeleted', $params);
    }

    public function fixWindowLineFeed($filePath)
    {
        $params = new StdClass;
        $params->filePath = $filePath;
        $this->queueTask('fixWindowLineFeed', $params);
    }

    /**
     * @param string $task
     * @param stdClass $params
     */
    protected function queueTask($task, stdClass $params)
    {
        $payload = new stdClass;

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