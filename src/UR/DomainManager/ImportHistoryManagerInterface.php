<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;

interface ImportHistoryManagerInterface extends ManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportedHistoryByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function replayDataSourceEntryData(DataSourceEntryInterface $dataSourceEntry);
}