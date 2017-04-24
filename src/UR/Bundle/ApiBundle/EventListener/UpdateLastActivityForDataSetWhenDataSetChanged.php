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

        $dataSet->setLastActivity(new DateTime());
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $args->getEntity();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $dataSet->setLastActivity(new DateTime());
    }
}