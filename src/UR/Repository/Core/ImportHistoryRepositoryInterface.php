<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface ImportHistoryRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $userRole
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getImportHistoriesForUserPaginationQuery(UserRoleInterface $userRole, PagerParam $param);

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
     * @param ImportHistoryInterface $importHistory
     * @param $limit
     * @return array
     */
    public function getImportedData(ImportHistoryInterface $importHistory, $limit = null);

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportedHistoryByDataSet(DataSetInterface $dataSet);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     */
    public function getImportedHistoryByConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ImportHistoryInterface $importHistory
     * @return mixed
     * @internal param DataSetInterface $dataSet
     */
    public function getImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource, ImportHistoryInterface $importHistory);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     */
    public function findImportHistoriesByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource);


    /**
     * @param ImportHistoryInterface $newImportHistory
     * @return mixed
     */
    public function findOldImportHistories(ImportHistoryInterface $newImportHistory);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntryWithoutDataSet(DataSourceEntryInterface $dataSourceEntry);
    /**
     * @param ImportHistoryInterface[] $importHistories
     * @return mixed
     */
    public function deleteImportedData($importHistories);

    public function deleteImportHistoryByDataSet(DataSetInterface $dataSet);

    public function deleteImportHistoriesByIds(array $importHistoryIds);

    /**
     * @param $importHistories
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     */
    public function deletePreviousImports($importHistories, ConnectedDataSourceInterface $connectedDataSource);

    public function deleteImportHistoryByConnectedDataSource($connectedDataSourceId);

    /**
     * @param ImportHistoryInterface $newImportHistory
     * @param $importHistoryIds
     * @return mixed
     */
    public function deleteImportedDataByImportHistoryIds(ImportHistoryInterface $newImportHistory, $importHistoryIds);

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getAllImportIdsFromDataSetTable(DataSetInterface $dataSet);
}