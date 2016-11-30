<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSourceEntryRepository extends EntityRepository implements DataSourceEntryRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'receivedDate' => 'receivedDate', 'fileName' => 'fileName'];

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

    /**
     * @inheritdoc
     */
    public function getDataSourceEntriesForUserQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user)->andWhere('dse.isActive=1');

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            if (!$user instanceof PublisherInterface) {
                $qb->join('dse.dataSource', 'ds');
            }
            $orX = $qb->expr()->orX();
            $conditions = array(
                $qb->expr()->like('dse.id', ':searchKey'),
                $qb->expr()->like('ds.name', ':searchKey'),
                $qb->expr()->like('ds.format', ':searchKey'),
                $qb->expr()->like('dse.receivedVia', ':searchKey')
            );
            $orX->addMultiple($conditions);

            $qb
                ->andWhere($orX)
                ->setParameter('searchKey', $searchLike);

            $searchLike = sprintf('%%%s%%', str_replace("/", "-", $param->getSearchKey()));
            $qb
                ->orWhere($qb->expr()->like('SUBSTRING(dse.receivedDate,0,10)', ':date'))
                ->setParameter('date', $searchLike);
        }
        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['receivedDate']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSourceEntriesForPublisherQuery($user) : $this->createQueryBuilder('dse');
    }

    public function getDataSourceEntriesByDataSourceIdQuery(DataSourceInterface $dataSource, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('dse')
            ->join('dse.dataSource', 'ds')
            ->where('dse.dataSource = :dataSource')
            ->andWhere('dse.isActive=1')
            ->setParameter('dataSource', $dataSource);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('ds.format', ':searchKey'),
                    $qb->expr()->like('dse.receivedVia', ':searchKey'),
                    $qb->expr()->like('dse.id', ':searchKey'),
                    $qb->expr()->like('dse.fileName', ':searchKey'),
                    $qb->expr()->like('dse.receivedDate', ':searchKey')
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
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['receivedDate']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['fileName']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    public function getDataSourceEntriesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getDataSourceEntriesForPublisherQuery($publisher, $limit, $offset);
        $qb->andWhere('dse.isActive=1');

        return $qb->getQuery()->getResult();
    }
}