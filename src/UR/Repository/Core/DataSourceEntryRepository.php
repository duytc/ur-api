<?php

namespace UR\Repository\Core;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSourceEntryRepository extends EntityRepository implements DataSourceEntryRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'receivedDate' => 'receivedDate', 'fileName' => 'fileName', 'endDate' => 'endDate'];

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
            $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
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
            $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
        } else {
            $qb->addOrderBy('dse.receivedDate', 'desc');
        }
        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntryIdsByDataSourceId(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dse')
            ->select('dse.id')
            ->join('dse.dataSource', 'ds')
            ->where('dse.dataSource = :dataSource')
            ->andWhere('dse.isActive=1')
            ->setParameter('dataSource', $dataSource);

        return $qb->getQuery()->getResult();
    }

    public function getDataSourceEntriesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getDataSourceEntriesForPublisherQuery($publisher, $limit, $offset);
        $qb->andWhere('dse.isActive=1');

        return $qb->getQuery()->getResult();
    }

    public function getImportedFileByHash(DataSourceInterface $dataSource, $hash)
    {
        $qb = $this->createQueryBuilder('dse');
        $qb->andWhere('dse.dataSource= :dataSource')
            ->andWhere('dse.hashFile = :hash')
            ->setParameter('dataSource', $dataSource)
            ->setParameter('hash', $hash);
        return $qb->getQuery()->getResult();
    }

    public function getDataSourceEntriesForTimeSeriesDataSource(DataSourceInterface $dataSource)
    {
        $qb1 = $this->createQueryBuilder('qb1')
            ->select('MAX(qb1.id)')
            ->addGroupBy('qb1.startDate')
            ->addGroupBy('qb1.endDate')
            ->where('qb1.dataSource = :dataSource')
            ->andWhere('qb1.startDate IS NOT NULL')
            ->andWhere('qb1.endDate IS NOT NULL');

        $qb = $this->createQueryBuilder('de');
        $timeSeriesEntries = $qb->where($qb->expr()->in('de.id', $qb1->getDQL()))
            ->setParameter('dataSource', $dataSource)
            ->getQuery()
            ->getResult();

        $qb = $this->createQueryBuilder('de');
        $emptyDateRangeEntries = $qb->where($qb->expr()->orX($qb->expr()->isNull('de.startDate'), $qb->expr()->isNull('de.endDate')))
            ->andWhere('de.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource)
            ->getQuery()
            ->getResult();

        return array_merge($timeSeriesEntries, $emptyDateRangeEntries);
    }


    /**
     * @inheritdoc
     */
    public function getDataSourceEntryForDataSourceByDate(DataSourceInterface $dataSource, \DateTime $dsNextTime)
    {
        $dsCurrentAlertTime = clone $dsNextTime;
        $dsCurrentAlertTime = $dsCurrentAlertTime->sub(new DateInterval('P1D'));

        $qb = $this->createQueryBuilder('dse');
        $qb->where('dse.dataSource= :dataSource')
            ->andWhere('dse.receivedDate>= :fromDate')
            ->andWhere('dse.receivedDate< :toDate')
            ->setParameter('dataSource', $dataSource)
            ->setParameter('fromDate', $dsCurrentAlertTime)
            ->setParameter('toDate', $dsNextTime);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return DataSourceEntryInterface|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLatestDataSourceEntryForDataSource(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dte')
            ->where('dte.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource->getId())
            ->addOrderBy('dte.receivedDate', 'ASC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntriesForDataSource($dataSource, $limit, $offset)
    {
        $dataSourceId = $dataSource->getId();

        $qb = $this->createQueryBuilder('dse')
            ->where('dse.dataSource = :dataSourceId')
            ->setParameter('dataSourceId', $dataSourceId, Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function getDataSourceEntriesForDataSourceOrderByStartDate(DataSourceInterface $dataSource)
    {
        if (!$dataSource->isDateRangeDetectionEnabled()) {
            return [];
        }

        return $this->createQueryBuilder('dse')
            ->where('dse.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource)
            ->addOrderBy('dse.endDate', 'asc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByDateRange(DataSourceInterface $dataSource, DateTime $startDate, DateTime $endDate)
    {
        $qb = $this->createQueryBuilder('dse')
            ->where('dse.dataSource = :dataSource')
            ->andWhere('dse.startDate = :startDate')
            ->andWhere('dse.endDate = :endDate')
            ->setParameter('dataSource', $dataSource)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->addOrderBy('dse.id', 'desc');

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getCleanUpEntries(DataSourceInterface $dataSource)
    {
        $qb1 = $this->createQueryBuilder('qb1')
            ->select('MAX(qb1.id)')
            ->addGroupBy('qb1.startDate')
            ->addGroupBy('qb1.endDate')
            ->where('qb1.dataSource = :dataSource')
            ->andWhere('qb1.startDate IS NOT NULL')
            ->andWhere('qb1.endDate IS NOT NULL')
            ->setParameter('dataSource', $dataSource);

        $qb = $this->createQueryBuilder('de');
        return $qb->where(
            $qb->expr()->notIn('de.id', $qb1->getDQL())
        )
            ->andWhere('de.dataSource = :dataSource')
            ->andWhere('de.startDate is not null')
            ->andWhere('de.endDate is not null')
            ->setParameter('dataSource', $dataSource)
            ->getQuery()
            ->getResult();
    }
}