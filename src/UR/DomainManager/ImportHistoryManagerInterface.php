<?php

namespace UR\DomainManager;

use Doctrine\DBAL\Query\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\PagerParam;

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

    /**
     * @param DataSetInterface $dataSet
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getImportedHistoryByDataSetQuery(DataSetInterface $dataSet, PagerParam $param);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function reImportDataSourceEntry(DataSourceEntryInterface $dataSourceEntry, DataSetInterface $dataSet);
}