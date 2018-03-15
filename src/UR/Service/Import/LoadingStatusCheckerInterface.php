<?php

namespace UR\Service\Import;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

interface LoadingStatusCheckerInterface
{
    /**
     * @param DataSetInterface $dataSet
     */
    public function postFileLoadingCompletedForDataSet(DataSetInterface $dataSet);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $concurrentLoadingFilesCountRedisKey
     */
    public function postFileLoadingCompletedForConnectedDatSource(ConnectedDataSourceInterface $connectedDataSource, $concurrentLoadingFilesCountRedisKey);
}