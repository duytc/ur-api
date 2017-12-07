<?php

namespace UR\Bundle\AppBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Worker\Manager;

class UpdateDateRangeForDataSourceEntryListener
{
    /**
     * @var Manager
     */
    protected $workerManager;

    protected $newEntries;

    /**
     * DataSourceEntryListener constructor.
     * @param Manager $workerManager
     */
    public function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
        $this->newEntries = [];
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

        $this->newEntries[] = $entity;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->newEntries) < 1) {
            return;
        }

        $newEntries = $this->newEntries;
        $this->newEntries = [];

        /** @var DataSourceEntryInterface $entity */
        foreach ($newEntries as $entity) {
            $changedDataSource = $entity->getDataSource();

            if (!$changedDataSource instanceof DataSourceInterface) {
                continue;
            }

            if ($changedDataSource->isDateRangeDetectionEnabled()) {
                $this->workerManager->updateDateRangeForDataSourceEntry($changedDataSource->getId(), $entity->getId());
            }
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $changedDataSource = $dataSourceEntry->getDataSource();
        if ($changedDataSource->isDateRangeDetectionEnabled()) {
            $this->workerManager->updateDateRangeForDataSource($changedDataSource->getId());
        }
    }
}