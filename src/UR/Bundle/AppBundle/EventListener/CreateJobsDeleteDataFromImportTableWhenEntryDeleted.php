<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\DataSourceEntryInterface;
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
class CreateJobsDeleteDataFromImportTableWhenEntryDeleted
{
    /**
     * @var array|ModelInterface[]
     */
    protected $dataSetAndImportHistories = [];

    /** @var Manager $workerManager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $importHistoryRepository = $entityManager->getRepository(ImportHistory::class);

        $importHistories = $importHistoryRepository->getImportHistoryByDataSourceEntryWithoutDataSet($dataSourceEntry);

        if (count($importHistories) < 1) {
            return;
        }

        $importHistoryByDataSet = [];
        /**@var ImportHistoryInterface $importHistory */
        foreach ($importHistories as $importHistory) {
            $importHistoryByDataSet[$importHistory->getDataSet()->getId()][] = $importHistory->getId();
        }

        $this->dataSetAndImportHistories = $importHistoryByDataSet;

    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->dataSetAndImportHistories) < 1) {
            return;
        }

        foreach ($this->dataSetAndImportHistories as $dataSetId => $importHistories) {
            $this->workerManager->undoImportHistories($importHistories, $dataSetId);
        }

        // reset for new onFlush event
        $this->dataSetAndImportHistories = [];
    }
}