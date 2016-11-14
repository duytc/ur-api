<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSetInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class DataSetChangeListener
 *
 * Handle event Data Set changed for updating
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportDataSetChangeListener
{

    protected $changedEntity;
    protected $insertedEntity;
    protected $removedEntity;

    /**
     * @var array|ModelInterface[]
     */
    protected $changedEntities = [];

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

        $this->changedEntities = array_merge($this->changedEntities, $uow->getScheduledEntityUpdates());

        $this->changedEntities = array_filter($this->changedEntities, function ($entity) {
            return $entity instanceof DataSetInterface;
        });
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->changedEntities as $entity) {
            if (!$entity instanceof DataSetInterface) {
                continue;
            }

            $changedFields = $uow->getEntityChangeSet($entity);
            $dataSetId = $entity->getId();
        }

        // reset for new onFlush event
        $this->changedEntities = [];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->insertedEntity = $entity;
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSetInterface) {
            return;
        }
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($entity);
        foreach ($changedFields as $field => $values) {
            if (strcmp($field, 'dimensions') === 0) {
                array_diff($values[0], $values[1]);
                $deletedDimensions = array_diff_key($values[0], $values[1]);
                $newDimensions = array_diff_key($values[1], $values[0]);
            }
        }
        foreach ($deletedDimensions as $deletedDimension) {
            foreach ($entity->getConnectedDataSources() as $connectedDataSource) {
                $mapFields = $connectedDataSource->getMapFields();
                $requires = $connectedDataSource->getRequires();
                $filters = $connectedDataSource->getFilters();
                $transforms = $connectedDataSource->getTransforms();
            }
        }

        $this->changedEntity = $entity;
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->removedEntity = $entity;
    }

}