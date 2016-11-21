<?php

namespace UR\DomainManager;

use Doctrine\DBAL\Query\QueryBuilder;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;

interface ConnectedDataSourceManagerInterface extends ManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSourceInterface $dataSource
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSourceByDataSource(DataSourceInterface $dataSource);

    /**
     * @param DataSetInterface $dataSet
     * @param PagerParam $params
     * @return QueryBuilder
     */
    public function getConnectedDataSourceByDataSetQuery(DataSetInterface $dataSet, PagerParam $params);
}