<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * Handle event ConnectedDataSource changed for updating
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportDataSourceEntryInsertedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->insertedEntities = array_merge($this->insertedEntities, $uow->getScheduledEntityInsertions());

        $this->insertedEntities = array_filter($this->insertedEntities, function ($entity) {
            return $entity instanceof DataSourceEntryInterface;
        });
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
            /** @var DataSourceInterface $dataSource */
            $dataSource = $entity->getDataSource();
            break;
        }
        // running import data
        foreach ($dataSource->getConnectedDataSources() as $connectedDataSource) {
            $this->workerManager->importDataWhenConnectedDataSourceChange($connectedDataSource->getId());
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }

}