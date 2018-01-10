<?php


namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface AutoOptimizationConfigRepositoryInterface extends ObjectRepository
{
    /**
     * remove a "add conditional transform value" id in "add conditional value transform"
     * @param $id
     * @return mixed
     */
    public function removeAddConditionalTransformValue($id);

    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param UserRoleInterface $userRole
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getAutoOptimizationConfigsForUserQuery(UserRoleInterface $userRole, PagerParam $param);
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getAutoOptimizationConfigsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);
}