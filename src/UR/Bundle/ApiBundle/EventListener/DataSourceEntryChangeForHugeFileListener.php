<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Worker\Manager;

class DataSourceEntryChangeForHugeFileListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;

    /**
     * MapBuilderChangeListener constructor.
     * @param LoggerInterface $logger
     * @param Manager $workerManager
     */
    public function __construct(LoggerInterface $logger, Manager $workerManager)
    {
        $this->logger = $logger;
        $this->workerManager = $workerManager;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSourceEntryInterface) {
            return;
        }

        $this->workerManager->splitHugeFile($entity->getId());
    }
}