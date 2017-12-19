<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;

class DeleteDataTrainingTableListener
{
    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $autoOptimizationConfig = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return;
        }

        $synchronize = new DataTrainingTableService($em, '');
        $synchronize->deleteDataTrainingTable($autoOptimizationConfig->getId());
    }
}