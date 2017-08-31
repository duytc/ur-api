<?php


namespace UR\Service\DataSource;

use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;

class CleanUpDataSourceTimeSeriesService implements CleanUpDataSourceTimeSeriesServiceInterface
{
    /** @var DataSourceEntryManagerInterface */
    protected $dataSourceEntryManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    /**
     * CleanUpDataSourceTimeSeriesService constructor.
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param ImportHistoryManagerInterface $importHistoryManager
     */
    public function __construct(DataSourceEntryManagerInterface $dataSourceEntryManager, ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importHistoryManager = $importHistoryManager;
    }

    /**
     * @inheritdoc
     */
    public function cleanUpDataSourceTimeSeries(DataSourceInterface $dataSource)
    {
        if (!$dataSource->getTimeSeries()) {
            return;
        }

        $dataSourceEntries = $this->dataSourceEntryManager->getCleanUpEntries($dataSource);

        foreach ($dataSourceEntries as $dataSourceEntry) {
            try {
                /** @var DataSourceEntryInterface $dataSourceEntry */
                $importHistories = $dataSourceEntry->getImportHistories();

                foreach ($importHistories as $importHistory) {
                    if (!$importHistory instanceof ImportHistoryInterface) {
                        continue;
                    }
                    
                    $this->importHistoryManager->delete($importHistory);
                }

                $this->dataSourceEntryManager->delete($dataSourceEntry);
            } catch (\Exception $e) {
                $e->getCode();
            }
        }
    }
}