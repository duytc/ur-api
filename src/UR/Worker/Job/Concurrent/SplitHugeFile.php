<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\Common\Collections\Collection;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
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

    /**
     * SplitHugeFile constructor.
     * @param LoggerInterface $logger
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param DataSourceFileFactory $dataSourceFileFactory
     * @param $fileSizeThreshold
     * @param Manager $manager
     */
    public function __construct(LoggerInterface $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, DataSourceFileFactory $dataSourceFileFactory, $fileSizeThreshold, Manager $manager)
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->fileSizeThreshold = $fileSizeThreshold;
        $this->manager = $manager;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @param JobParams $params
     * @throws \Exception
     * @throws \Pubvantage\Worker\Exception\MissingJobParamException
     * @throws \UR\Service\Import\ImportDataException
     * @throws \UR\Service\PublicSimpleException
     */
    public function run(JobParams $params)
    {
        $dataSourceEntryId = $params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface || !$dataSourceEntry->getDataSource() instanceof DataSourceInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            return;
        }

        $dataSource = $dataSourceEntry->getDataSource();
        $fileSize = filesize($this->dataSourceFileFactory->getAbsolutePath($dataSourceEntry->getPath()));

        if ($fileSize > $this->fileSizeThreshold) {
            try {
                $dataSourceEntry = $this->dataSourceFileFactory->splitHugeFile($dataSourceEntry);
                $this->dataSourceEntryManager->save($dataSourceEntry);
            } catch (Exception $e) {

            }
        }

        /** Update total row */
        $this->manager->updateTotalRowWhenEntryInserted($dataSourceEntry->getId());

        /** Detect date range */
        $this->manager->updateDateRangeForDataSourceEntry($dataSource->getId(), $dataSourceEntry->getId());

        /** Load to data sets */
        if ($dataSource->getEnable()) {
            $connectedDataSources = $dataSource->getConnectedDataSources();
            $connectedDataSources = $connectedDataSources instanceof Collection ? $connectedDataSources->toArray() : $connectedDataSources;

            foreach ($connectedDataSources as $connectedDataSource) {
                if (!$connectedDataSource instanceof ConnectedDataSourceInterface || !$connectedDataSource->getDataSet() instanceof DataSetInterface) {
                    continue;
                }
                $this->manager->loadingDataSourceEntriesToDataSetTable($connectedDataSource->getId(), [$dataSourceEntry->getId()], $connectedDataSource->getDataSet()->getId());
            }
        }
    }
}