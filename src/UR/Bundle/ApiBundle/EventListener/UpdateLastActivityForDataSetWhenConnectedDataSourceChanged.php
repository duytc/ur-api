<?php

namespace UR\Bundle\ApiBundle\EventListener;


use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ConnectedDataSourceInterface;


/**
 * Class UpdateLastActivityForDataSet
 * update last Activity for data set when connected data source inserted or updated
 */
class UpdateLastActivityForDataSetWhenConnectedDataSourceChanged
{
    /**
     * @var array|ConnectedDataSourceInterface[]
     */
    protected $dataSetToBeUpdatedList = [];

    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $args->getEntity();
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $connectedDataSource->setLastActivity(new DateTime());

        $this->dataSetToBeUpdatedList[] = $connectedDataSource->getDataSet();
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($connectedDataSource);
        if (array_key_exists('lastActivity', $changedFields)) {
            return;
        }

        $connectedDataSource->setLastActivity(new DateTime());
        $this->dataSetToBeUpdatedList[] = $connectedDataSource->getDataSet();
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->dataSetToBeUpdatedList) < 1) {
            return;
        }

        $em = $args->getEntityManager();

        $uniqueDataSets = [];
        foreach ($this->dataSetToBeUpdatedList as $dataSetTobeUpdated) {
            $uniqueDataSets[$dataSetTobeUpdated->getId()] = $dataSetTobeUpdated;
        }

        foreach ($uniqueDataSets as $uniqueDataSet) {
            $uniqueDataSet->setLastActivity(new DateTime());
            $em->persist($uniqueDataSet);
        }

        $this->dataSetToBeUpdatedList = [];

        $em->flush();
    }
}