<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSetRepository extends EntityRepository implements DataSetRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    /**
     * @inheritdoc
     */
    public function getDataSetsForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getDataSetsForPublisherQuery($publisher, $limit, $offset)
            ->addOrderBy('ds.name', 'asc');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSetsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('ds')
            ->where('ds.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);

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
    public function getDataSetsForUserPaginationQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('ds.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }
        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('ds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('ds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSetsForPublisherQuery($user) : $this->createQueryBuilder('ds');
    }

}