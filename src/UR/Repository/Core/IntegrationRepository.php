<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class IntegrationRepository extends EntityRepository implements IntegrationRepositoryInterface
{
    public function getIntegrationsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.integrationPublishers', 'ip')
            ->select('i')
            ->where('ip.publisher = :publisher')
            ->orWhere('i.enableForAllUsers = :enableForAllUsers')
            ->setParameter('publisher', $publisher)
            ->setParameter('enableForAllUsers', true);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }

    /**
     * @param UserRoleInterface $user
     * @return QueryBuilder
     */
    public function getIntegrationsForUserQuery(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getIntegrationsForPublisherQuery($user) : $this->createQueryBuilder('a');
    }
}