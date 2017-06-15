<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportWhenDataSourceEntryInsertedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var Manager $workerManager */
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

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1) {
            return;
        }

        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof DataSourceEntryInterface) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                $dataSource = $entity->getDataSource();
                foreach ($dataSource->getConnectedDataSources() as $connectedDataSource) {
                    $this->workerManager->loadingDataSourceEntriesToDataSetTable($connectedDataSource->getId(), [$entity->getId()], $connectedDataSource->getDataSet()->getId());
                }
            }
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }
}