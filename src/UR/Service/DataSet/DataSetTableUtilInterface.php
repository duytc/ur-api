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
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    public function getEntriesByDateRange(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate);
}