<?php

namespace UR\Service\Import;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;

interface AutoImportDataInterface
{
    /**
     * @param ConnectedDataSourceInterface[] $connectedDataSources
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function autoCreateDataImport($connectedDataSources, DataSourceEntryInterface $dataSourceEntry);
}