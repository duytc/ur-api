<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
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
}