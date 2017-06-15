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
class UpdateDataSourceEntryTotalRowListener
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

        foreach ($this->insertedEntities as $dataSourceEntry) {
            $this->workerManager->updateTotalRowWhenEntryInserted($dataSourceEntry->getId());
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }
}