<?php

namespace UR\Service\Import;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;

interface AutoImportDataInterface
{
    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function autoCreateDataImport(DataSourceEntryInterface $dataSourceEntry);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSources
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function createDryRunImportData(ConnectedDataSourceInterface $connectedDataSources, DataSourceEntryInterface $dataSourceEntry);
}