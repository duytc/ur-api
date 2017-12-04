<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class IntegrationRepository extends EntityRepository implements IntegrationRepositoryInterface
{
    public function getIntegrationsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb
            ->leftJoin('i.integrationTags', 'it')
            ->leftJoin('it.tag', 't')
            ->leftJoin('t.userTags', 'ut')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('i.enableForAllUsers', true),
                    $qb->expr()->andX(
                        $qb->expr()->eq('ut.publisher', ':publisher'),
                        $qb->expr()->isNotNull('it.tag')
                    )
                )
            )
            ->select('i')
            ->setParameter('publisher', $publisher);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }


        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getIntegrationsForUserQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb =  $user instanceof PublisherInterface ? $this->getIntegrationsForPublisherQuery($user) : $this->createQueryBuilder('i');

        return $qb->orderBy('i.name');
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.integrationTags', 'it')
            ->where('it.tag = :tag')
            ->setParameter('tag', $tag);

        return $qb->getQuery()->getResult();
    }
}