<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\ModelInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
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

    /**
     * @var array
     */
    protected $deletedDateRanges = [];

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

        $dataSetId = $importHistory->getDataSet()->getId();

        $this->deletedImportHistoryIds[$dataSetId][] = $importHistory->getId();

        if (!array_key_exists($dataSetId, $this->deletedDateRanges)) {
            $this->deletedDateRanges[$dataSetId] = [];
        }

        $this->deletedDateRanges[$dataSetId][] = [
            $importHistory->getDataSourceEntry()->getStartDate(),
            $importHistory->getDataSourceEntry()->getEndDate()
        ];
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->deletedImportHistoryIds) < 1) {
            return;
        }

        /** @var ImportHistoryInterface $importHistory */
        foreach ($this->deletedImportHistoryIds as $dataSetId => $importHistoryIds) {
            $deletedDateRange = (array_key_exists($dataSetId, $this->deletedDateRanges))
                ? $this->getUnionChangedDateRangeForDataSet($this->deletedDateRanges[$dataSetId])
                : [];

            $this->workerManager->undoImportHistories($importHistoryIds, $dataSetId, $deletedDateRange);
        }

        // reset for new onFlush event
        $this->deletedImportHistoryIds = [];
        $this->deletedDateRanges = [];
    }

    /**
     * get Union Changed Date Range
     *
     * @param array $deletedDateRanges array of date ranges
     * @return array
     */
    private function getUnionChangedDateRangeForDataSet(array $deletedDateRanges)
    {
        $startDate = null;
        $endDate = null;

        foreach ($deletedDateRanges as $deletedDateRange) {
            if (!is_array($deletedDateRange)) {
                continue;
            }

            if (is_null($startDate)) {
                $startDate = $deletedDateRange[0];
            }

            if (is_null($endDate)) {
                $endDate = $deletedDateRange[1];
            }

            // override min startDate
            if ($deletedDateRange[0] < $startDate) {
                $startDate = $deletedDateRange[0];
            }

            // override max endDate
            if ($deletedDateRange[1] > $endDate) {
                $endDate = $deletedDateRange[1];
            }
        }

        return [
            ($startDate instanceof \DateTime) ? $startDate->format(DateFormat::DEFAULT_DATE_FORMAT) : null,
            ($endDate instanceof \DateTime) ? $endDate->format(DateFormat::DEFAULT_DATE_FORMAT) : null
        ];
    }
}