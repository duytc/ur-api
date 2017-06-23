<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\ConnectedDataSourceInterface;
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
        $qb = $this->createQueryBuilder('ih')
            ->distinct()
            ->where('ih.dataSet=:dataSet')
            ->setParameter('dataSet', $dataSet);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource, ImportHistoryInterface $importHistory)
    {
        $qb = $this->createQueryBuilder('ih')
            ->where('ih.dataSourceEntry=:dataSourceEntry')
            ->andWhere('ih.connectedDataSource=:connectedDataSource')
            ->andWhere('ih.id<>:importId')
            ->setParameter('dataSourceEntry', $dataSourceEntry)
            ->setParameter('connectedDataSource', $connectedDataSource)
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
            if (!$importHistory instanceof ImportHistoryInterface) {
                continue;
            }
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $importHistory->getDataSet()->getId());
            $query = sprintf('DELETE FROM %s WHERE %s = ?', $conn->quoteIdentifier($tableName), $conn->quoteIdentifier('__import_id'));
            $stmt = $conn->prepare($query);
            $stmt->bindValue(1, $importHistory->getId());
            $stmt->execute();
            $this->_em->remove($importHistory);

            //TODO UPDATE TOTAL ROW
//            $this->updateDataSetTotalRow($importHistory->getDataSet()->getId());
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

    public function deleteImportHistoryByDataSet(DataSetInterface $dataSet)
    {
        $conn = $this->_em->getConnection();
        $query = sprintf('DELETE FROM core_import_history WHERE data_set_id = ?');
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $dataSet->getId());
        return $stmt->execute();
    }

    public function deleteImportHistoryByConnectedDataSource($connectedDataSourceId)
    {
        $conn = $this->_em->getConnection();
        $query = sprintf('DELETE FROM core_import_history WHERE connected_data_source_id = ?');
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $connectedDataSourceId);
        return $stmt->execute();
    }

    public function deleteImportHistoriesByIds(array $importHistoryIds)
    {
        if (count($importHistoryIds) < 1) {
            return 0;
        }

        $conn = $this->_em->getConnection();
        $query = sprintf('DELETE FROM core_import_history WHERE id IN (%s)', implode(',', $importHistoryIds));
        $stmt = $conn->prepare($query);
        return $stmt->execute();
    }

    public function deletePreviousImports($importHistories, ConnectedDataSourceInterface $connectedDataSource)
    {
        $conn = $this->_em->getConnection();
        /**@var ImportHistoryInterface $importHistory */
        foreach ($importHistories as $importHistory) {
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $importHistory->getDataSet()->getId());
            $query = sprintf('DELETE FROM %s WHERE %s = ?', $conn->quoteIdentifier($tableName), $conn->quoteIdentifier('__import_id'));
            $stmt = $conn->prepare($query);
            $stmt->bindValue(1, $importHistory->getId());
            $stmt->execute();
            $this->_em->remove($importHistory);

            //TODO update total rows of data set
//            $this->updateDataSetTotalRow($importHistory->getDataSet()->getId());
        }

        $this->_em->flush();
        $conn->close();
    }
}