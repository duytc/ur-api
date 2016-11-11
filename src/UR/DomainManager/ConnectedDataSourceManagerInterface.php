<?php

namespace UR\DomainManager;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

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
}