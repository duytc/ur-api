<?php

namespace UR\Bundle\AppBundle\EventListener;


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
class DataSetChangeListener
{
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

        /** @var array|int[] $dataSetIds */
        $dataSetIds = [];
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        // filter all sites changed on rtb & exchanges, then build needBeUpdatedAdSlots
        foreach ($this->changedEntities as $entity) {
            if (!$entity instanceof DataSetInterface) {
                continue;
            }

            $changedFields = $uow->getEntityChangeSet($entity);

//            if (array_key_exists('rtbStatus', $changedFields)) {
//                $needToBeUpdatedSiteIds[] = $entity->getId();
//            }
            $dataSetIds[]=$entity->getId();
        }

        // update connected DataSource for DataSet
//        $this->workerManager->updateConnectedDataSourceForDataSet($dataSetIds);

        // reset for new onFlush event
        $this->changedEntities = [];
    }

}