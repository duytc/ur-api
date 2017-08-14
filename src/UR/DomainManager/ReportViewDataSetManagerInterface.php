<?php

namespace UR\DomainManager;

use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewDataSetManagerInterface extends ManagerInterface
{
    /**
     * @param UserRoleInterface $reportView
     * @param PagerParam $param
     * @param $multiView
     * @return QueryBuilder
     */
    public function getReportViewsForUserPaginationQuery(UserRoleInterface $reportView, PagerParam $param, $multiView);

    /**
     * get Report Views By Data Set
     *
     * @param DataSetInterface $dataSet
     * @return ReportViewInterface[]
     */
    public function getReportViewsByDataSet(DataSetInterface $dataSet);
}