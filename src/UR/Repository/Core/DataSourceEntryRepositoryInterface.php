<?php

namespace UR\Repository\Core;

use DateTime;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface DataSourceEntryRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getDataSourceEntriesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getDataSourceEntriesForUserQuery(UserRoleInterface $user, PagerParam $param);

    /**
     * @param DataSourceInterface $dataSource
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getDataSourceEntriesByDataSourceIdQuery(DataSourceInterface $dataSource, PagerParam $param);

    /**
     * @param DataSourceInterface $dataSource
     * @return array
     */
    public function getDataSourceEntryIdsByDataSourceId(DataSourceInterface $dataSource);

    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getDataSourceEntriesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param DataSourceInterface $dataSource
     * @param string $hash
     * @return mixed
     */
    public function getImportedFileByHash(DataSourceInterface $dataSource, $hash);

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function getDataSourceEntriesForTimeSeriesDataSource(DataSourceInterface $dataSource);

    /**
     * @param DataSourceInterface $dataSource
     * @param $dsNextTime
     * @return mixed
     */
    public function getDataSourceEntryForDataSourceByDate(DataSourceInterface $dataSource, \DateTime $dsNextTime);

    /**
     * @param DataSourceInterface $dataSource
     * @return DataSourceEntryInterface|null
     */
    public function getLatestDataSourceEntryForDataSource(DataSourceInterface $dataSource);

    /**
     * @param DataSourceInterface $dataSource
     * @param $limit
     * @param $offset
     * @return mixed
     */
    public function getDataSourceEntriesForDataSource($dataSource, $limit, $offset);

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function getDataSourceEntriesForDataSourceOrderByStartDate(DataSourceInterface $dataSource);

    /**
     * @param DataSourceInterface $dataSource
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    public function findByDateRange(DataSourceInterface $dataSource, DateTime $startDate, DateTime $endDate);

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function getCleanUpEntries(DataSourceInterface $dataSource);
}