<?php

namespace UR\DomainManager;

use Doctrine\ORM\QueryBuilder;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewManagerInterface extends ManagerInterface
{
    /**
     * @param UserRoleInterface $reportView
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getReportViewsForUserPaginationQuery(UserRoleInterface $reportView, PagerParam $param);
}