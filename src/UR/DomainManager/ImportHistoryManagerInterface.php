<?php

namespace UR\DomainManager;

use Doctrine\ORM\QueryBuilder;
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
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ImportHistoryInterface $importHistory
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource, ImportHistoryInterface $importHistory);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     */
    public function findImportHistoriesByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource);

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
    public function deleteImportedData($importHistories);

    public function deleteImportHistoryByDataSet(DataSetInterface $dataSet);

    public function deleteImportHistoryByConnectedDataSource($connectedDataSourceId);

    public function deleteImportHistoriesByIds(array $importHistoryIds);

    public function deleteImportHistoryByConnectedDataSourceAndEntry ($connectedDataSourceId, $dataSourceEntryId);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return ImportHistory
     */
    public function createImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource);
}