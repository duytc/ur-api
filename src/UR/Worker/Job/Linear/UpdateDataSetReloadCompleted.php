<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\EventDispatcher\EventDispatcher;
use UR\Bundle\ApiBundle\Event\DataSetReloadCompletedEvent;

class UpdateDataSetReloadCompleted implements JobInterface
{
    const JOB_NAME = 'updateDataSetReloadCompleted';

    const DATA_SET_ID = 'data_set_id';
    const IS_FROM_PARSE_CHUNK_FILE = 'is_from_parse_chunk_file';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, $eventDispatcher)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $isFromParseChunkFile = (bool)$params->getParam(self::IS_FROM_PARSE_CHUNK_FILE);

        $this->logger->notice(sprintf('Updating for Data Set %d after reloading completed', $dataSetId));
        $this->eventDispatcher->dispatch(DataSetReloadCompletedEvent::EVENT_NAME, new DataSetReloadCompletedEvent($dataSetId, $isFromParseChunkFile));
    }
}