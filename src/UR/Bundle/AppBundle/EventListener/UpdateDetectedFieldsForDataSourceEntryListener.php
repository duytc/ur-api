<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateDetectedFieldsForDataSourceEntryListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];
    protected $deletedEntities = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->insertedEntities[] = $dataSourceEntry;
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->deletedEntities[] = $dataSourceEntry;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1 && count($this->deletedEntities) < 1) {
            return;
        }

        /** @var DataSourceEntryInterface $dataSourceEntry */
        foreach ($this->deletedEntities as $dataSourceEntry) {
            // important: data source entry may be deleted by cascade when data source is deleted
            // so that, make sure the data source is existed before do other actions

            $dataSource = $dataSourceEntry->getDataSource();
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $this->workerManager->updateDetectedFieldsWhenEntryDeleted($dataSource->getFormat(), $dataSourceEntry->getPath(), $dataSource->getId());
        }

        $em = $args->getEntityManager();

        foreach ($this->insertedEntities as &$dataSourceEntry) {
            $em->persist($dataSourceEntry);
        }

        $dataSourceEntryIds = array_map(function ($dataSourceEntry) {
            /** @var DataSourceEntryInterface $dataSourceEntry */
            return $dataSourceEntry->getId();
        }, $this->insertedEntities);

        // reset for new onFlush event
        $this->insertedEntities = [];
        $this->deletedEntities = [];

        // flush changes
        $em->flush();

        foreach ($dataSourceEntryIds as $dataSourceEntryId) {
            $this->workerManager->updateDetectedFieldsWhenEntryInserted($dataSourceEntryId);
        }
    }
}