<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Service\DataSet\UpdateNumOfPendingLoad;

class DataSetImportJobChangeListener
{
    /** @var  UpdateNumOfPendingLoad */
    private $updateNumOfPendingLoad;

    /**
     * DataSetImportJobChangeListener constructor.
     * @param UpdateNumOfPendingLoad $updateNumOfPendingLoad
     */
    public function __construct(UpdateNumOfPendingLoad $updateNumOfPendingLoad)
    {
        $this->updateNumOfPendingLoad = $updateNumOfPendingLoad;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSetImportJobInterface) {
            return;
        }

        $this->updateNumOfPendingLoad->updateNumberOfPendingLoadForDataSet($entity->getDataSet(), $args->getEntityManager());
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSetImportJobInterface) {
            return;
        }

        $this->updateNumOfPendingLoad->updateNumberOfPendingLoadForDataSet($entity->getDataSet(), $args->getEntityManager());
    }
}