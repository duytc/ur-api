<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * Handle event when a Data source connected to Data Set for importing data from that data source
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ImportDataWhenCreateConnectedDataSourceListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntity;

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->importWhenInsertOrUpdate($this->insertedEntity);
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->insertedEntity = $entity;
    }

    public function importWhenInsertOrUpdate($entity)
    {
        if (!$entity instanceof ConnectedDataSourceInterface) {
            return;
        }
        $entryIds = [];
        /**@var DataSourceEntryInterface $dataSourceEntry */
        foreach ($entity->getDataSource()->getDataSourceEntries() as $dataSourceEntry) {
            $entryIds[] = $dataSourceEntry->getId();
        }

        $this->workerManager->reImportWhenNewEntryReceived($entryIds);
    }


}