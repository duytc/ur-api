<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\AlertPagerParam;
use UR\Model\Core\DataSourceInterface;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface UserTagRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param TagInterface $tag
     * @return mixed
     */
    public function findByTag(TagInterface $tag);
}