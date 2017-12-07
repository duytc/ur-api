<?php


namespace UR\Service\DataSet;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

interface DataSetTableUtilInterface
{
    /**
     * @param DataSetInterface $dataSet
     */
    public function updateIndexes(DataSetInterface $dataSet);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ReloadParams $reloadParameter
     * @return mixed
     */
    public function getEntriesByReloadParameter(ConnectedDataSourceInterface $connectedDataSource, ReloadParams $reloadParameter);
}