<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
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
     * @param int $dataSetId
     * @param int $importId
     * @return QueryBuilder
     */
    public function getImportedDataByIdQuery($dataSetId, $importId);

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportedDataByDataSet(DataSetInterface $dataSet);
}