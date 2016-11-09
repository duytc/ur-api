<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\OnFlushEventArgs;
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
    protected $changedEntities = [];

    /** @var Manager */
    private $workerManager;

    private $uploadFileDir;

    function __construct(Manager $workerManager, $uploadFileDir)
    {
        $this->workerManager = $workerManager;
        $this->uploadFileDir = $uploadFileDir;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->changedEntities = array_merge($this->changedEntities, $uow->getScheduledEntityUpdates());

        $this->changedEntities = array_filter($this->changedEntities, function ($entity) {
            return $entity instanceof ConnectedDataSourceInterface;
        });
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        /** @var array|int $dataSetId */
        $dataSetId = [];
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->changedEntities as $entity) {
            if (!$entity instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $changedFields = $uow->getEntityChangeSet($entity);
            $dataSetId = $entity->getDataSet()->getId();
        }

        // running import data
        $this->workerManager->autoCreateDataImport($dataSetId, $this->uploadFileDir);

        // reset for new onFlush event
        $this->changedEntities = [];
    }

}