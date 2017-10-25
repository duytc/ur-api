<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return QueryBuilder
     */
    public function getReportViewsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @param null $multiView
     * @return QueryBuilder
     */
    public function getReportViewsForUserPaginationQuery(UserRoleInterface $user, PagerParam $param, $multiView = null);

    /**
     * @param UserRoleInterface $user
     * @return QueryBuilder
     */
    public function getDataSourceHasMultiViewForUserQuery(UserRoleInterface $user);

    /**
     * @param UserRoleInterface $user
     * @return QueryBuilder
     */
    public function getDataSourceHasNotMultiViewForUserQuery(UserRoleInterface $user);

    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function getMultiViewReportForPublisher(PublisherInterface $publisher);

    /**
     * @return mixed
     */
    public function getReportViewHasMaximumId();

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getReportViewThatUseDataSet(DataSetInterface $dataSet);

    /**
     * @param $reportViewId
     */
    public function updateLastRun($reportViewId);

    /**
     * get Report Views By Data Set
     *
     * @param DataSetInterface $dataSet
     * @return ReportViewInterface[]
     */
    public function getReportViewsByDataSet(DataSetInterface $dataSet);

    public function getReportViewByIds(array $ids);

    /**
     * @return mixed
     */
    public function getSingleViews();

    /**
     * @param ReportViewInterface $reportView
     * @return null|ReportViewInterface
     */
    public function hasSubviews(ReportViewInterface $reportView);

	/**
	 * remove a "add conditional transform value" id in "add conditional value transform"
	 * @param $id
	 * @return mixed
	 */
	public function removeAddConditionalTransformValue($id);
}