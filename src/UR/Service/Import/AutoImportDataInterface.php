<?php

namespace UR\Service\Import;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;

interface AutoImportDataInterface
{
    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ImportHistoryInterface $importHistoryEntity
     * @return mixed
     */
    public function loadingDataFromFileToDatabase(ConnectedDataSourceInterface $connectedDataSource, DataSourceEntryInterface $dataSourceEntry, ImportHistoryInterface $importHistoryEntity);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSources
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSources, DataSourceEntryInterface $dataSourceEntry);
}