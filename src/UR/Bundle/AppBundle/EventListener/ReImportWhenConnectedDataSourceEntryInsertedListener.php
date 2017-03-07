<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ConnectedDataSourceInterface;
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
class ReImportWhenConnectedDataSourceEntryInsertedListener
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
        $this->insertedEntities = array_merge($this->insertedEntities, $uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates());

        $this->insertedEntities = array_filter($this->insertedEntities, function ($entity) {
            return $entity instanceof ConnectedDataSourceInterface;
        });
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1) {
            return;
        }

        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof ConnectedDataSourceInterface) {
                continue;
            }
            if (!$entity->isReplayData()) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                /** @var Collection|DataSourceEntryInterface[] $dataSourceEntries */
                $dataSourceEntries = $entity->getDataSource()->getDataSourceEntries();
                if ($dataSourceEntries instanceof Collection) {
                    $dataSourceEntries = $dataSourceEntries->toArray();
                }

                foreach ($dataSourceEntries as $dataSourceEntry) {
                    $this->workerManager->loadingDataFromFileToDataImportTable($entity->getId(), $dataSourceEntry->getId(), $entity->getDataSet()->getId());
                }
            }
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }
}