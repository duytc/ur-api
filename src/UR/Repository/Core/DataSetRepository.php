<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSetRepository extends EntityRepository implements DataSetRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name', 'lastActivity' => 'lastActivity'];

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
    public function getDataSetsForUserPaginationQuery(UserRoleInterface $user, PagerParam $param, $hasConnectedDataSource = null)
    {
        $qb = $this->createQueryBuilderForUser($user);

        switch ($hasConnectedDataSource) {
            case 'true':
                $qb = $this->getDataSetHasConnectedDataSourceQuery($user);
                break;
            case 'false':
                $qb = $this->getDataSetHasNotConnectedDataSourceQuery($user);
                break;
        }

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
                case $this->SORT_FIELDS['lastActivity']:
                    $qb->addOrderBy('ds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return QueryBuilder
     */
    public function getDataSetByDataSourceQuery(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('ds')
            ->join('ds.connectedDataSources', 'cds')
            ->where('cds.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource)
            ->andWhere('ds.publisher = :publisherId')
            ->setParameter('publisherId', $dataSource->getPublisherId());
        return $qb;
    }

    /**
     * @param UserRoleInterface $publisher
     * @return QueryBuilder
     */
    public function getDataSetHasConnectedDataSourceQuery(UserRoleInterface $publisher)
    {
        $qb = $this->createQueryBuilderForUser($publisher)
            ->join('ds.connectedDataSources', 'cds');

        return $qb;
    }

    /**
     * @param UserRoleInterface $publisher
     * @return array
     */
    public function getDataSetHasConnectedDataSource(UserRoleInterface $publisher)
    {
        $qb = $this->getDataSetHasConnectedDataSourceQuery($publisher);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param UserRoleInterface $publisher
     * @return QueryBuilder
     */
    public function getDataSetHasNotConnectedDataSourceQuery(UserRoleInterface $publisher)
    {
        $dataSets = $this->getDataSetHasConnectedDataSource($publisher);

        $qb = $this->createQueryBuilderForUser($publisher);

        $dataSetIds = [];
        foreach ($dataSets as $dataSet) {
            /**@var DataSetInterface $dataSet */
            $dataSetIds[] = $dataSet->getId();
        }

        $qb
            ->andWhere('ds.id NOT IN (:dataSetIds)')
            ->setParameter('dataSetIds', implode($dataSetIds, ', '));

        return $qb;
    }

    public function getDataSetHasNotConnectedDataSource(UserRoleInterface $publisher)
    {
        $qb = $this->getDataSetHasNotConnectedDataSourceQuery($publisher);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSetByDataSource(DataSourceInterface $dataSource)
    {
        $qb = $this->getDataSetByDataSourceQuery($dataSource);

        return $qb->getQuery()->getResult();
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSetsForPublisherQuery($user) : $this->createQueryBuilder('ds');
    }

    /**
     * @inheritdoc
     */
    public function getDataSetByName($dataSetName, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('ds')
            ->where('ds.name = :name')
            ->setParameter('name', $dataSetName);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

}