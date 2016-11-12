<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * Handle event ConnectedDataSource changed for updating
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ConnectedDataSourceChangeListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $changedEntity;
    protected $insertedEntity;
    protected $removedEntity;

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->importWhenInsertOrUpdate($this->insertedEntity);
        $this->importWhenInsertOrUpdate($this->changedEntity);
        $this->importWhenDeleted($this->removedEntity);
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->insertedEntity = $entity;
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->changedEntity = $entity;
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->removedEntity = $entity;
    }

    public function importWhenInsertOrUpdate($entity)
    {
        if (!$entity instanceof ConnectedDataSourceInterface) {
            return;
        }
        $this->workerManager->importDataWhenConnectedDataSourceChange($entity->getId());
    }

    public function importWhenDeleted($entity)
    {
        if (!$entity instanceof ConnectedDataSourceInterface) {
            return;
        }
        $this->workerManager->reImportWhenDataSetChange($entity->getDataSet()->getId());
    }


}