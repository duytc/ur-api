<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\ConnectedDataSource;
use UR\Entity\Core\DataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class CountNumberOfChangesWhenConnectedDataSourceChangeListener
{
    protected $changingConnectedDataSources = [];
    protected $deletingConnectedDataSources = [];

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        // only increase connected data source noChanges on creating if enable replayData
        if (!$connectedDataSource->isReplayData()) {
            $this->changingConnectedDataSources[] = $connectedDataSource;
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        if ($args->hasChangedField('mapFields') || $args->hasChangedField('transforms') || $args->hasChangedField('filters') || $args->hasChangedField('requires')) {
            $this->changingConnectedDataSources[] = $connectedDataSource;
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $this->deletingConnectedDataSources[] = $connectedDataSource;
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        if (count($this->changingConnectedDataSources) < 1 && count($this->deletingConnectedDataSources) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        // handle changed connectedDataSources
        /** @var ConnectedDataSourceInterface $changingConnectedDataSource */
        foreach ($this->changingConnectedDataSources as $changingConnectedDataSource) {
            $changingConnectedDataSource->increaseNoChanges();
            $md = $em->getClassMetadata(ConnectedDataSource::class);
            $uow->recomputeSingleEntityChangeSet($md, $changingConnectedDataSource);

            /** @var DataSetInterface $dataSet */
            $dataSet = $changingConnectedDataSource->getDataSet();
            $dataSet->increaseNoConnectedDataSourceChanges(); // increase by 1

            $md = $em->getClassMetadata(DataSet::class);
            $uow->recomputeSingleEntityChangeSet($md, $dataSet);
        }

        $this->changingConnectedDataSources = [];

        // handle deleting connectedDataSources
        /** @var ConnectedDataSourceInterface $deletingConnectedDataSource */
        foreach ($this->deletingConnectedDataSources as $deletingConnectedDataSource) {
            /** @var DataSetInterface $dataSet */
            $dataSet = $deletingConnectedDataSource->getDataSet();
            $dataSet->decreaseNoConnectedDataSourceChanges($deletingConnectedDataSource->getNoChanges()); // decrease by current deleting connected data source changes

            if ($dataSet->getNoConnectedDataSourceChanges() === 0) {
                // also reset data set noChanges when all connected data source have no changes
                $dataSet->setNoChanges(0);
            }

            $md = $em->getClassMetadata(DataSet::class);
            $uow->recomputeSingleEntityChangeSet($md, $dataSet);
        }

        $this->deletingConnectedDataSources = [];
    }
}