<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
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
     * @param ImportHistoryInterface $importHistory
     * @return QueryBuilder
     */
    public function getImportedData(ImportHistoryInterface $importHistory);

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportedHistoryByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry, DataSetInterface $dataSet);

    /**
     * @param ImportHistoryInterface[] $importHistories
     * @return mixed
     */
    public function deleteImportedData($importHistories);
}