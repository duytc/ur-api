<?php

namespace UR\Repository\Core;

use Box\Spout\Writer\Exception\InvalidSheetNameException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSource\Collection;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;

class ImportHistoryRepository extends EntityRepository implements ImportHistoryRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'dataSet' => 'dataSet', 'dataSource' => 'dataSource', 'createDate' => 'createDate'];

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
            ->setParameter('dataSet', $dataSet);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('ih.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        $searchLike = sprintf('%%%s%%', str_replace("/", "-", $param->getSearchKey()));
        $qb
            ->orWhere($qb->expr()->like('SUBSTRING(ih.createdDate,0,10)', ':date'))
            ->setParameter('date', $searchLike);

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
                case $this->SORT_FIELDS['createDate']:
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
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry)
    {
        $qb = $this->createQueryBuilder('ih')->where('ih.dataSourceEntry=:dataSourceEntry')->setParameter('dataSourceEntry', $dataSourceEntry);
        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function replayDataSourceEntryData(DataSourceEntryInterface $dataSourceEntry)
    {
        $importHistories = $this->getImportHistoryByDataSourceEntry($dataSourceEntry);
        $this->deleteImportedData($importHistories);
    }

    /**
     * @inheritdoc
     */
    public function deleteImportedData($importHistories)
    {
        $conn = $this->_em->getConnection();
        /**@var ImportHistoryInterface $importHistory */
        foreach ($importHistories as $importHistory) {
            $query = "delete from " . sprintf(\UR\Service\DataSet\Type::PREFIX_DATA_IMPORT_TABLE, $importHistory->getDataSet()->getId()) . " where __import_id = ?";
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
        $conn = $this->_em->getConnection();

        $query = "select * from " . sprintf(\UR\Service\DataSet\Type::PREFIX_DATA_IMPORT_TABLE, $importHistory->getDataSet()->getId()) . " where __import_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $importHistory->getId());
        $stmt->execute();
        $results = $stmt->fetchAll();
        $conn->close();
        if (count($results) < 1)
            return $results;

        $result = $results[0];
        $parserConfig = new ParserConfig();
        $collection = new Collection($results);
        foreach ($result as $column => $row) {
            $parserConfig->addColumn($column);
        }

        $connDataSources = $importHistory->getDataSet()->getConnectedDataSources();
        $connDataSource = null;
        foreach ($connDataSources as $item) {
            if ($item->getDataSource()->getId() === $importHistory->getDataSourceEntry()->getDataSource()->getId()) {
                $connDataSource = $item;
                break;
            }
        }
        $transforms = $connDataSource->getTransforms();
        foreach ($transforms as $transform) {
            if (TransformType::isTransformSingleField($transform[TransformType::TRANSFORM_TYPE])) {
                if (strcmp($transform[TransformType::TYPE], TransformType::DATE) === 0) {
                    $parserConfig->transformColumn($transform[TransformType::FIELD], new DateFormat('Y-m-d', $transform[TransformType::TO]));
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::NUMBER) === 0) {
                    $parserConfig->transformColumn($transform[TransformType::FIELD], new NumberFormat($transform[TransformType::DECIMALS], $transform[TransformType::THOUSANDS_SEPARATOR]));
                }
            }
        }
        $parser = new Parser();
        $collection = $parser->parse($collection, $parserConfig);

        return $collection->getRows();
    }
}