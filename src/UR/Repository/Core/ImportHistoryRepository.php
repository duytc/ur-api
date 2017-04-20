<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Service\DataSet\Synchronizer;

class ImportHistoryRepository extends EntityRepository implements ImportHistoryRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'dataSet' => 'dataSet', 'dataSource' => 'dataSource'];

    /**
     * @inheritdoc
     */
    public function getImportHistoriesForUserPaginationQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.id', ':searchKey'),
                    $qb->expr()->like('ih.id', ':searchKey')
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
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['dataSet']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getImportedHistoryByDataSetQuery(DataSetInterface $dataSet, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('ih')
            ->leftJoin('ih.dataSourceEntry', 'dse')
            ->leftJoin('dse.dataSource', 'ds')
            ->where('ih.dataSet=:dataSet')
            ->setParameter('dataSet', $dataSet)->addOrderBy('ih.createdDate', 'desc');

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('ih.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);

            $searchLike = sprintf('%%%s%%', str_replace("/", "-", $param->getSearchKey()));
            $qb
                ->orWhere($qb->expr()->like('ih.createdDate', ':date'))
                ->setParameter('date', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['dataSource']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getImportedHistoryByDataSet(DataSetInterface $dataSet)
    {
        $qb = $this->createQueryBuilder('ih')->where('ih.dataSet=:dataSet')->setParameter('dataSet', $dataSet);
        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry, DataSetInterface $dataSet, ImportHistoryInterface $importHistory)
    {
        $qb = $this->createQueryBuilder('ih')
            ->where('ih.dataSourceEntry=:dataSourceEntry')
            ->andWhere('ih.dataSet=:dataSet')
            ->andWhere('ih.id<>:importId')
            ->setParameter('dataSourceEntry', $dataSourceEntry)
            ->setParameter('dataSet', $dataSet)
            ->setParameter('importId', $importHistory->getId());
        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getImportHistoryByDataSourceEntryWithoutDataSet(DataSourceEntryInterface $dataSourceEntry)
    {
        $qb = $this->createQueryBuilder('ih')
            ->where('ih.dataSourceEntry=:dataSourceEntry')
            ->setParameter('dataSourceEntry', $dataSourceEntry);
        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function deleteImportedData($importHistories)
    {
        $conn = $this->_em->getConnection();
        /**@var ImportHistoryInterface $importHistory */
        foreach ($importHistories as $importHistory) {
            $query = "delete from " . sprintf(Synchronizer::PREFIX_DATA_IMPORT_TABLE, $importHistory->getDataSet()->getId()) . " where __import_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(1, $importHistory->getId());
            $stmt->execute();
            $this->_em->remove($importHistory);
        }
        $this->_em->flush();
        $conn->close();
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSetsForPublisherQuery($user) : $this->createQueryBuilder('ih')->join('ih.dataSet', 'ds');
    }

    /**
     * @inheritdoc
     */
    public function getDataSetsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('ih')
            ->join('ih.dataSet', 'ds')
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
    public function getImportedData(ImportHistoryInterface $importHistory)
    {
        $dataSetId = $importHistory->getDataSet()->getId();
        $conn = $this->_em->getConnection();

        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dimensions = $importHistory->getDataSet()->getDimensions();
        $metrics = $importHistory->getDataSet()->getMetrics();
        $fields = array_merge($dimensions, $metrics);
        $selectedFields = [];

        foreach ($fields as $field => $type) {
            $selectedFields[] = 'd.' . $field;
        }

        $qb = $conn->createQueryBuilder();
        $query = $qb->select($selectedFields)
            ->from($dataSetSynchronizer->getDataSetImportTableName($dataSetId), 'd')
            ->where('d.__import_id = ' . $importHistory->getId());

        $stmt = $query->execute();
        $results = $stmt->fetchAll();
        $conn->close();

        return $results;
    }

    /**
     * @param DataSourceInterface $dataSource
     * @param PagerParam $param
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getImportedHistoryByDataSourceQuery(DataSourceInterface $dataSource, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('ih')
            ->leftJoin('ih.dataSourceEntry', 'dse')
            ->leftJoin('dse.dataSource', 'ds')
            ->where('dse.dataSource=:dataSource')
            ->setParameter('dataSource', $dataSource);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('ih.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);

            $searchLike = sprintf('%%%s%%', str_replace("/", "-", $param->getSearchKey()));
            $qb
                ->orWhere($qb->expr()->like('ih.createdDate', ':date'))
                ->setParameter('date', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['dataSet']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['createdDate']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }
}