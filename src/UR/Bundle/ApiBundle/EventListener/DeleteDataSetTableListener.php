<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;

class DeleteDataSetTableListener
{
    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $dataSet = $args->getEntity();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $synchronize = new Synchronizer($args->getEntityManager()->getConnection(), new Comparator());
        $synchronize->deleteDataSetImportTable($dataSet->getId());
    }
}