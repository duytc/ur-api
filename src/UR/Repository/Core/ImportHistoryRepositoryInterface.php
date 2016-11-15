<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function getImportedDataByIdQuery($dataSetId, $importId);
}