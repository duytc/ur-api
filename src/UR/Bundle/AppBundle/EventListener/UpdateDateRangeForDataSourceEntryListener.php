<?php

namespace UR\Bundle\AppBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Worker\Manager;

class UpdateDateRangeForDataSourceEntryListener
{
    /**
     * @var Manager
     */
    protected $workerManager;

    protected $newEntities;

    /**
     * DataSourceEntryListener constructor.
     * @param Manager $workerManager
     */
    public function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
        $this->newEntities = [];
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

        $this->newEntities[] = $entity;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->newEntities) < 1) {
            return;
        }

        /** @var DataSourceEntryInterface $entity */
        foreach ($this->newEntities as $entity) {
            $changedDataSource = $entity->getDataSource();
            if ($changedDataSource->isDateRangeDetectionEnabled()) {
                // make sure we detect data source entry date range before updating its data source date range
                $this->workerManager->updateDateRangeForDataSourceEntry($changedDataSource->getId(), $entity->getId());
                $this->workerManager->updateDateRangeForDataSource($changedDataSource->getId());
            }
        }

        $this->newEntities = [];
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