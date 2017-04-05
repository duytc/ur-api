<?php

namespace UR\DomainManager;

use Doctrine\DBAL\Query\QueryBuilder;
use UR\Entity\Core\ImportHistory;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
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
     * @param DataSetInterface $dataSet
     * @param ImportHistoryInterface $importHistory
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry, DataSetInterface $dataSet, ImportHistoryInterface $importHistory);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntryWithoutDataSet(DataSourceEntryInterface $dataSourceEntry);

    /**
     * @param DataSetInterface $dataSet
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getImportedHistoryByDataSetQuery(DataSetInterface $dataSet, PagerParam $param);

    /**
     * @param DataSourceInterface $dataSource
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getImportedHistoryByDataSourceQuery(DataSourceInterface $dataSource, PagerParam $param);

    /**
     * @param ImportHistoryInterface[] $importHistories
     * @return mixed
     */
    public function deletePreviousImports($importHistories);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return ImportHistory
     */
    public function createImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource);
}