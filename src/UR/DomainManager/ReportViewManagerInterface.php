<?php

namespace UR\DomainManager;

use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
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
     * create token for reportView with fields to be shared
     *
     * @param ReportViewInterface $reportView
     * @param array $fieldsToBeShared
     * @param array|string|null $dateRange
     * @param bool $allowDatesOutside
     * @return mixed
     */
    public function createTokenForReportView(ReportViewInterface $reportView, array $fieldsToBeShared, $dateRange = null, $allowDatesOutside = false);

    /**
     * get Report Views By Data Set
     *
     * @param DataSetInterface $dataSet
     * @return ReportViewInterface[]
     */
    public function getReportViewsByDataSet(DataSetInterface $dataSet);

    /**
     * @return mixed
     */
    public function getSingleViews();


    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return QueryBuilder
     */
    public function getReportViewsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);
}