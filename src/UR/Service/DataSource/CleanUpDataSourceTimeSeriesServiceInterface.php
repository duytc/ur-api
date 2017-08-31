<?php


namespace UR\Service\DataSource;

use UR\Model\Core\DataSourceInterface;

interface CleanUpDataSourceTimeSeriesServiceInterface
{
    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function cleanUpDataSourceTimeSeries(DataSourceInterface $dataSource);
}