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

        $entryIds = [];
        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof DataSourceEntryInterface) {
                continue;
            }

            $entryIds[] = $entity->getId();
        }
        // running import data
        $this->workerManager->reImportWhenNewEntryReceived($entryIds);

        // reset for new onFlush event
        $this->insertedEntities = [];
    }

}