<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSourceRepository extends EntityRepository implements DataSourceRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name', 'lastActivity' => 'lastActivity'];

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

    public function getDataSourceNotInByDataSetQuery(DataSetInterface $dataSet)
    {
        $dataSources = $this->getDataSourceByDataSet($dataSet);
        $dataSourceIds = [];
        foreach ($dataSources as $dataSource) {
            $dataSourceIds[] = $dataSource->getId();
        }

        $qb = $this->createQueryBuilder('ds')
            ->where('ds.publisher = :publisher')
            ->setParameter('publisher', $dataSet->getPublisher());

        $qb
            ->andWhere('ds.id NOT IN (:dataSourceIds)')
            ->setParameter('dataSourceIds', implode($dataSourceIds, ', '));

        return $qb;
    }

    public function getDataSourceNotInByDataSet(DataSetInterface $dataSet)
    {
        $qb = $this->getDataSourceNotInByDataSetQuery($dataSet);

        return $qb->getQuery()->getResult();
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
                case $this->SORT_FIELDS['lastActivity']:
                    $qb->addOrderBy('ds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        } else {
            $qb->addOrderBy('ds.lastActivity', 'desc');
        }
        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourcesByIntegrationAndPublisher(IntegrationInterface $integration, PublisherInterface $publisher)
    {
        $qb = $this->getDataSourcesForPublisherQuery($publisher);

        /* join integration */
        $qb
            ->join('ds.dataSourceIntegrations', 'dsi')
            ->andWhere('dsi.integration = :integrationId')
            ->setParameter('integrationId', $integration->getId());

        return $qb->getQuery()->getResult();
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSourcesForPublisherQuery($user) : $this->createQueryBuilder('ds');
    }

    /**
     * @return DataSourceInterface[]
     */
    public function getDataSourcesHasDailyAlert()
    {
        $qb = $this->createQueryBuilder('ds');
        $qb->where('ds.nextAlertTime is not null');

        return $qb->getQuery()->getResult();
    }

    public function getBrokenDateRangeDataSourceForDataSets(array $dataSetIds)
    {
        $qb = $this->createQueryBuilder('dts');

        return $qb->join('dts.connectedDataSources', 'cnt')
            ->join('cnt.dataSet', 'ds')
            ->where('dts.dateRangeDetectionEnabled = 1')
            ->andWhere($qb->expr()->in('ds.id', $dataSetIds))
            ->getQuery()
            ->getResult();
    }
}