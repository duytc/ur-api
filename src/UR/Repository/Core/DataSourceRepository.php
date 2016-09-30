<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\User\Role\PublisherInterface;

class DataSourceRepository extends EntityRepository implements DataSourceRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getDataSourcesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getDataSourcesForPublisherQuery($publisher, $limit, $offset)
            ->addOrderBy('n.name', 'asc');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSourcesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('n')->where('n.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);
        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }
}