<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface OptimizationRuleRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $userRole
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getOptimizationRulesForUserQuery(UserRoleInterface $userRole, PagerParam $param);

    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getOptimizationRulesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getOptimizationRulesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param ReportViewInterface $reportView
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getOptimizationRulesForReportView(ReportViewInterface $reportView, $limit = null, $offset = null);

    /**
     * @param DataSetInterface $dataSet
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getOptimizationRulesForDataSet(DataSetInterface $dataSet, $limit = null, $offset = null);

    /**
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function hasReportView(ReportViewInterface $reportView);
}