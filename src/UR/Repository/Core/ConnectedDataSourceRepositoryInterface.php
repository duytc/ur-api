<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

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
}