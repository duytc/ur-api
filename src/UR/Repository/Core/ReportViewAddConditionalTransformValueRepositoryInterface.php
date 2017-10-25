<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewAddConditionalTransformValueRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $user
     * @param array $ids
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getReportViewAddConditionalTransformValueQuery(UserRoleInterface $user, array $ids, PagerParam $param);

    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getReportViewAddConditionalTransformValueForPubQuery(PublisherInterface $publisher, $limit = null, $offset = null);
}