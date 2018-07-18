<?php


namespace UR\Bundle\ApiBundle\EventListener;


use UR\Bundle\ApiBundle\Event\DataSetReloadCompletedEvent;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use UR\Service\RedLock;

class UnlockAutoReloadAugmentationDataSetLockListener
{
    /**
     * @var DataSetRepositoryInterface
     */
    private $dataSetRepository;

    /** @var RedLock */
    private $redLock;

    /** @var string */
    private $lockKeyPrefix;

    /**
     * ChangesCountingListener constructor.
     * @param DataSetRepositoryInterface $dataSetRepository
     * @param RedLock $redLock
     * @param string $lockKeyPrefix
     */
    public function __construct(DataSetRepositoryInterface $dataSetRepository, RedLock $redLock, $lockKeyPrefix)
    {
        $this->dataSetRepository = $dataSetRepository;
        $this->redLock = $redLock;
        $this->lockKeyPrefix = $lockKeyPrefix;
    }

    /**
     * @param DataSetReloadCompletedEvent $event
     */
    public function onDataSetReloadCompleted(DataSetReloadCompletedEvent $event)
    {
        $dataSetId = $event->getDataSetId();
        $isFromParseChunkFile = $event->isIsFromParseChunkFile();
        $dataSet = $this->dataSetRepository->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }
        if (!$isFromParseChunkFile) {
            // check map data set contains large file
            /** @var ConnectedDataSourceInterface[] $connectedDataSources */
            $connectedDataSources = $dataSet->getConnectedDataSources();
            foreach ($connectedDataSources as $connectedDataSource) {
                if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                    continue;
                }
                $dataSource = $connectedDataSource->getDataSource();
                if (!$dataSource instanceof DataSourceInterface) {
                    continue;
                }
                $dataSourceEntries = $dataSource->getDataSourceEntries();
                foreach ($dataSourceEntries as $dataSourceEntry) {
                    if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                        continue;
                    }
                    if (!empty($dataSourceEntry->getChunks())) {
                        return;
                    }
                }
            }
        }

        $lock_key = $this->lockKeyPrefix . $dataSetId;
        $this->redLock->unlockKey($lock_key);
    }
}