<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\ModelInterface;
use UR\Worker\Manager;

/**
 * Class CreateJobsDeleteDataFromImportTableWhenEntryDeleted
 *
 * when a file deleted, delete imported data from the entry
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class CreateJobsDeleteDataFromImportTableWhenImportHistoriesDeleted
{
    /**
     * @var array|ModelInterface[]
     */
    protected $deletedImportHistoryIds = [];

    /** @var Manager $workerManager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var ImportHistoryInterface $importHistory */
        $importHistory = $args->getEntity();

        if (!$importHistory instanceof ImportHistoryInterface) {
            return;
        }

        $this->deletedImportHistoryIds[$importHistory->getDataSet()->getId()][] = $importHistory->getId();
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->deletedImportHistoryIds) < 1) {
            return;
        }

        /** @var ImportHistoryInterface $importHistory */
        foreach ($this->deletedImportHistoryIds as $dataSetId => $importHistoryIds) {
            $this->workerManager->undoImportHistories($importHistoryIds, $dataSetId);
        }

        // reset for new onFlush event
        $this->deletedImportHistoryIds = [];
    }
}