<?php

namespace UR\DomainManager;

use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewManagerInterface extends ManagerInterface
{
    /**
     * @param UserRoleInterface $reportView
     * @param PagerParam $param
     * @param $multiView
     * @return QueryBuilder
     */
    public function getReportViewsForUserPaginationQuery(UserRoleInterface $reportView, PagerParam $param, $multiView);

    /**
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function checkIfReportViewBelongsToMultiView(ReportViewInterface $reportView);

    /**
     * create token for reportView with fields to be shared
     *
     * @param ReportViewInterface $reportView
     * @param array $fieldsToBeShared
     * @return mixed
     */
    public function createTokenForReportView(ReportViewInterface $reportView, array $fieldsToBeShared);
}