<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\EventDispatcher\EventDispatcher;
use UR\Bundle\ApiBundle\Event\ConnectedDataSourceReloadCompletedEvent;

class UpdateConnectedDataSourceReloadCompleted implements JobInterface
{
    const JOB_NAME = 'updateConnectedDataSourceReloadCompleted';

    const DATA_SET_ID = 'data_set_id';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';

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
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        $this->logger->notice(sprintf('Update for Connected Data Source %d after reloading completed', $connectedDataSourceId));
        $this->eventDispatcher->dispatch(ConnectedDataSourceReloadCompletedEvent::EVENT_NAME, new ConnectedDataSourceReloadCompletedEvent($connectedDataSourceId));
    }
}