<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface DataSetRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getDataSetsForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getDataSetsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param UserRoleInterface $userRole
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getDataSetsForUserPaginationQuery(UserRoleInterface $userRole, PagerParam $param);

    /**
     * @param DataSourceInterface $dataSource
     * @return DataSetInterface[]
     */
    public function getDataSetByDataSource(DataSourceInterface $dataSource);
}