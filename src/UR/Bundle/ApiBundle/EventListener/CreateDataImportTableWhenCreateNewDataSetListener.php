<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;

class CreateDataImportTableWhenCreateNewDataSetListener
{
    /**
     * handle event postPersist one dataset, this auto create empty data import table with name __data_import_{dataSetId}
     *
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        $conn = $em->getConnection();
        $synchronizer = new Synchronizer($conn, new Comparator());
        $synchronizer->createEmptyDataSetTable($entity);
    }
}