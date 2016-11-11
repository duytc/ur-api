<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * Handle event ConnectedDataSource changed for updating
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class DataSourceEntryDeletedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $deletedEntities = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->deletedEntities = array_merge($this->deletedEntities, $uow->getScheduledEntityDeletions());

        $this->deletedEntities = array_filter($this->deletedEntities, function ($entity) {
            return $entity instanceof DataSourceEntryInterface;
        });
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->deletedEntities) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->deletedEntities as $entity) {
            if (!$entity instanceof DataSourceEntryInterface) {
                continue;
            }

            $changedFields = $uow->getEntityChangeSet($entity);
            /** @var DataSourceInterface $dataSource */
            $dataSource = $entity->getDataSource();
            break;
        }
        // running import data
        foreach ($dataSource->getConnectedDataSources() as $connectedDataSource) {
            $this->workerManager->importDataWhenConnectedDataSourceChange($connectedDataSource->getId());
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }

}