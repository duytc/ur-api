<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use UR\Bundle\ApiBundle\Event\DataSetReloadCompletedEvent;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use UR\Repository\Core\ImportHistoryRepositoryInterface;
use UR\Worker\Job\Concurrent\ParseChunkFile;

class ResetLoadingRedisKeysListener
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DataSetRepositoryInterface
     */
    private $dataSetRepository;

    /** @var ImportHistoryRepositoryInterface */
    private $importHistoryRepository;

    /** @var \Redis */
    private $redis;

    /**
     * ChangesCountingListener constructor.
     * @param EntityManagerInterface $em
     * @param \Redis $redis
     */
    public function __construct(EntityManagerInterface $em, \Redis $redis)
    {
        $this->em = $em;
        $this->dataSetRepository = $em->getRepository(DataSet::class);
        $this->importHistoryRepository = $em->getRepository(ImportHistory::class);
        $this->redis = $redis;
    }

    /**
     * @param DataSetReloadCompletedEvent $event
     */
    public function onDataSetReloadCompleted(DataSetReloadCompletedEvent $event)
    {
        $dataSetId = $event->getDataSetId();
        $dataSet = $this->dataSetRepository->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        // Delete keys as "import_history_811_chunks_finish".
        $keys = $this->getLoadingRedisKeysFromConnectedDataSource($dataSet->getConnectedDataSources());
        $this->deleteRedisKeys($keys);

        // Delete keys contain ur-data-set-323, as
        //    ur:pending_job_count_ur-data-set-323
        //    ur_lock_worker_ur-data-set-323
        //    ur_lock_worker_ur-data-set-323-e13087fa87fa9dc1704b94e53
        // except keys due to job worker design:
        //    ur:linear_tube_expire_job_ur-data-set-323
        //    ur_linear_job_next_priority_tube_ur-data-set-323
        //
        // May be remove if causing side effects
        $dataSetKeys = $this->getAllKeysLoadingDataSet($dataSet);
        $dataSetKeys = array_values(array_filter($dataSetKeys, function ($key) {
            return strpos($key, 'ur:linear_tube_expire_job_ur-data-set-') !== false && strpos($key, 'ur_linear_job_next_priority_tube_ur-data-set-') !== false;
        }));
        $this->deleteRedisKeys($dataSetKeys);
    }

    /**
     * @param $connectedDataSources
     * @return mixed
     */
    private function getLoadingRedisKeysFromConnectedDataSource($connectedDataSources)
    {
        $importHistories = $this->getAllImportHistoriesByConnectedDataSources($connectedDataSources);

        $keys = [];

        foreach ($importHistories as $importHistory) {
            if (!$importHistory instanceof ImportHistoryInterface) {
                continue;
            }

            $keys = array_merge($keys, $this->getRedisKeysFromImportHistory($importHistory));
        }

        return $keys;
    }

    /**
     * @param $connectedDataSources
     * @return array
     */
    private function getAllImportHistoriesByConnectedDataSources($connectedDataSources)
    {
        if ($connectedDataSources instanceof Collection) {
            $connectedDataSources = $connectedDataSources->toArray();
        }

        $importHistories = [];

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $importHistories = array_merge($importHistories, $this->importHistoryRepository->getImportedHistoryByConnectedDataSource($connectedDataSource));
        }

        return $importHistories;
    }

    /**
     * @param ImportHistoryInterface $importHistory
     * @return mixed
     */
    private function getRedisKeysFromImportHistory(ImportHistoryInterface $importHistory)
    {
        $keys[] = sprintf(ParseChunkFile::FLAG_IMPORT_COMPLETED_CHUNKS, $importHistory->getId());

        return $keys;
    }

    /**
     * @param $keys
     */
    private function deleteRedisKeys($keys)
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }

    /**
     * @param DataSetInterface $dataSet
     * @return array
     */
    private function getAllKeysLoadingDataSet(DataSetInterface $dataSet)
    {
        $linearTubeName = DataSetLoadFilesConcurrentJobScheduler::getDataSetTubeName($dataSet->getId());

        return array_unique(array_merge(
            $this->redis->keys(sprintf("*%s-*", $linearTubeName)),
            $this->redis->keys(sprintf("*%s", $linearTubeName))
        ));
    }
}