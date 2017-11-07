<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Worker\Manager;

class DataSourceEntryChangeForHugeFileListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;
    private $newDataSourceEntries = [];

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

        $this->newDataSourceEntries[] = $entity;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->newDataSourceEntries) < 1) {
            return;
        }

        $entries = $this->newDataSourceEntries;
        $this->newDataSourceEntries = [];

        foreach ($entries as $entry) {
            if (!$entry instanceof DataSourceEntryInterface) {
                continue;
            }
            $this->workerManager->splitHugeFile($entry->getId());
        }
    }
}