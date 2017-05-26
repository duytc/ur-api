<?php

namespace UR\Bundle\ApiBundle\EventListener;


use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSetInterface;


/**
 * Class UpdateLastActivityForDataSet
 * update last Activity for data set when connected data source inserted or updated
 */
class UpdateLastActivityForDataSetWhenDataSetChanged
{
    /**
     * @var array|DataSetInterface[]
     */
    protected $dataSetToBeUpdatedList = [];

    public function prePersist(LifecycleEventArgs $args)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $args->getEntity();
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($dataSet);

        if (count($changedFields) == 1 && array_key_exists('numOfPendingLoad', $changedFields)) {
            return;
        }

        $dataSet->setLastActivity(new DateTime());
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $args->getEntity();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($dataSet);

        if (count($changedFields) == 1 && array_key_exists('numOfPendingLoad', $changedFields)) {
            return;
        }

        $dataSet->setLastActivity(new DateTime());
    }
}