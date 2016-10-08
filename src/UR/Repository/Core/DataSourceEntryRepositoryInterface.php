<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface DataSourceEntryRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getDataSourceEntriesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public  function  getDataSourceEntriesForDataSourceQuery(UserRoleInterface $user, PagerParam $param);
}