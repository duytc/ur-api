<?php

namespace UR\DomainManager;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

interface ConnectedDataSourceManagerInterface extends ManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet);
}