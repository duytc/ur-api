<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;

interface ConnectedDataSourceRepositoryInterface extends ObjectRepository
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
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getConnectedDataSourceByDataSetQuery(DataSetInterface $dataSet, PagerParam $param);
}