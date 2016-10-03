<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\User\Role\PublisherInterface;

class DataSourceEntryRepository extends EntityRepository implements DataSourceEntryRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getDataSourceEntriesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('dse')
            ->join('dse.dataSource', 'ds')
            ->andWhere('ds.publisher = :publisherId')
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