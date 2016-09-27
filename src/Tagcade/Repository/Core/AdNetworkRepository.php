<?php

namespace Tagcade\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Tagcade\Model\User\Role\PublisherInterface;

class AdNetworkRepository extends EntityRepository implements AdNetworkRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getAdNetworksForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getAdNetworksForPublisherQuery($publisher, $limit, $offset)
            ->addOrderBy('n.name', 'asc');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getAdNetworksForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('n')
            ->where('n.publisher = :publisher_id')
            ->setParameter('publisher_id', $publisherId, Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }
}