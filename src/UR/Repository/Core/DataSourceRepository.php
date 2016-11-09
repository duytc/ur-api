<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSourceRepository extends EntityRepository implements DataSourceRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];
    const WRONG_FORMAT = 'wrongFormat';
    const DATA_RECEIVED = 'dataReceived';
    /**
     * @inheritdoc
     */
    public function getDataSourcesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getDataSourcesForPublisherQuery($publisher, $limit, $offset)
            ->addOrderBy('ds.name', 'asc');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSourcesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
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
    public function getDataSourceByApiKey($apiKey)
    {
        $qb = $this->createQueryBuilder('ds')
            ->where('ds.apiKey = :apiKeyParam')
            ->setParameter('apiKeyParam', $apiKey, Type::STRING);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceByEmailKey($emailKey)
    {
        $qb = $this->createQueryBuilder('ds')
            ->where('ds.urEmail = :urEmailParam')
            ->setParameter('urEmailParam', $emailKey, Type::STRING);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceByDataSetQuery(DataSetInterface $dataSet)
    {
        $qb = $this->createQueryBuilder('ds')
            ->join('ds.connectedDataSources', 'cds')
            ->where('cds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->andWhere('ds.publisher = :publisherId')
            ->setParameter('publisherId', $dataSet->getPublisherId());

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceByDataSet(DataSetInterface $dataSet)
    {
        $qb = $this->getDataSourceByDataSetQuery($dataSet);

        return $qb->getQuery()->getResult();
    }

    public function getDataSourceNotInByDataSet(DataSetInterface $dataSet)
    {
        $inQb = $this->getDataSourceByDataSetQuery($dataSet);

        $allQb = $this->getDataSourcesForPublisherQuery($dataSet->getPublisher());

        $notIn = array_diff($allQb->getQuery()->getResult(), $inQb->getQuery()->getResult());

        return $notIn;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourcesForUserQuery(UserRoleInterface $user, PagerParam $param)
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
        return $user instanceof PublisherInterface ? $this->getDataSourcesForPublisherQuery($user) : $this->createQueryBuilder('ds');
    }

}