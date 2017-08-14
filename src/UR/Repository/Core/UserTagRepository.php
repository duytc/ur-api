<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceInterface;
use UR\Model\AlertPagerParam;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class UserTagRepository extends EntityRepository implements UserTagRepositoryInterface
{

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        return $this->createQueryBuilder('ut')
            ->where('ut.publisher = :publisher')
            ->setParameter('publisher', $publisher)->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        return $this->createQueryBuilder('ut')
            ->where('ut.tag = :tag')
            ->setParameter('tag', $tag)->getQuery()->getResult();
    }
}