<?php

namespace UR\DomainManager;

use DateTime;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceEntryManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param DataSourceInterface $dataSource
     * @param DateTime $dsNextTime
     * @return mixed
     */
    public function getDataSourceEntryToday(DataSourceInterface $dataSource, DateTime $dsNextTime);

    /**
     * @param DataSourceInterface $dataSource
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function findByDataSource($dataSource, $limit = null, $offset = null);

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