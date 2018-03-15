<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\Common\Collections\Collection;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Worker\Manager;

class SplitHugeFile implements JobInterface
{
    const JOB_NAME = 'split_huge_file';
    const DATA_SOURCE_ENTRY_ID = 'data_source_entry_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var DataSourceFileFactory */
    private $dataSourceFileFactory;

    private $fileSizeThreshold;

    /** @var Manager */
    private $manager;

    /** @var \Redis */
    private $redis;

    private $lockKeyPrefix;

    /**
     * SplitHugeFile constructor.
     * @param LoggerInterface $logger
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param DataSourceFileFactory $dataSourceFileFactory
     * @param $fileSizeThreshold
     * @param Manager $manager
     * @param \Redis $redis
     * @param $lockKeyPrefix
     */
    public function __construct(LoggerInterface $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, DataSourceFileFactory $dataSourceFileFactory, $fileSizeThreshold, Manager $manager, \Redis $redis, $lockKeyPrefix)
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->fileSizeThreshold = $fileSizeThreshold;
        $this->manager = $manager;
        $this->redis = $redis;
        $this->lockKeyPrefix = $lockKeyPrefix;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSourceEntryId = (int)$params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            return;
        }

        $fileSize = filesize($this->dataSourceFileFactory->getAbsolutePath($dataSourceEntry->getPath()));

        if ($fileSize > $this->fileSizeThreshold) {
            try {
                $dataSourceEntry = $this->dataSourceFileFactory->splitHugeFile($dataSourceEntry);
                $this->dataSourceEntryManager->save($dataSourceEntry);
            } catch (Exception $e) {

            }
        }

        /** Update total row */
        if (empty($dataSourceEntry->getTotalRow())) {
            $this->manager->updateTotalRowWhenEntryInserted($dataSourceEntry->getId());
        }

        /** Detect date range */
        if ($dataSourceEntry->getDataSource()->isDateRangeDetectionEnabled()) {
            $this->manager->updateDateRangeForDataSourceEntry($dataSourceEntry->getDataSource()->getId(), $dataSourceEntry->getId());
        }

        /** Load to data sets */
        if ($dataSourceEntry->getDataSource()->getEnable()) {
            $dataSource = $dataSourceEntry->getDataSource();
            $connectedDataSources = $dataSource->getConnectedDataSources();
            if ($connectedDataSources instanceof Collection) {
                $connectedDataSources = $connectedDataSources->toArray();
            }

            // advance: group connected data sources by data set id
            // then we could add jobs for each data set id by only one action, this reduces process linear jobs!!!
            $filterConnectedDataSourceByDataSetIds = [];
            foreach ($connectedDataSources as $connectedDataSource) {
                if (!$connectedDataSource instanceof ConnectedDataSourceInterface || !$connectedDataSource->getDataSet() instanceof DataSetInterface) {
                    continue;
                }

                $filterConnectedDataSourceByDataSetIds[$connectedDataSource->getDataSet()->getId()][] = $connectedDataSource;
            }

            foreach ($filterConnectedDataSourceByDataSetIds as $dataSetId => $mapConnectedDataSources) {
                if (!is_array($mapConnectedDataSources)) {
                    continue;
                }

                /** Unlock data set */
                $linearTubeName = DataSetLoadFilesConcurrentJobScheduler::getDataSetTubeName($dataSetId);
                $dataSetLockKey = sprintf("%s%s", $this->lockKeyPrefix, $linearTubeName);
                $this->redis->del($dataSetLockKey);

                $connectedDataSourceIds = array_map(function ($mapConnectedDataSource) {
                    /** @var ConnectedDataSourceInterface $mapConnectedDataSource */
                    return $mapConnectedDataSource->getId();
                }, $mapConnectedDataSources);

                /* Create jobs import to database */
                $this->manager->loadingDataSourceEntriesToDataSetTable($connectedDataSourceIds, [$dataSourceEntry->getId()], $dataSetId);
           }
        }
    }
}