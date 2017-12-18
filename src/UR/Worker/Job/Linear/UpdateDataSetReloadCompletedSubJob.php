<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\EventDispatcher\EventDispatcher;
use UR\Bundle\ApiBundle\Event\DataSetReloadCompletedEvent;

class UpdateDataSetReloadCompletedSubJob implements JobInterface
{
    const JOB_NAME = 'updateDataSetReloadCompletedSubJob';

    const DATA_SET_ID = 'data_set_id';

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

        $this->logger->notice(sprintf('Updating for Data Set %d after reloading completed', $dataSetId));
        $this->eventDispatcher->dispatch(DataSetReloadCompletedEvent::EVENT_NAME, new DataSetReloadCompletedEvent($dataSetId));
    }
}