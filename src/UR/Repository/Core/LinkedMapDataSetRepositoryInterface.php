<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

interface LinkedMapDataSetRepositoryInterface extends ObjectRepository
{
    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getByMapDataSet(DataSetInterface $dataSet);

    /**
     * @param $mapDataSet
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param array $mappedFields
     * @return mixed
     */
    public function override($mapDataSet, ConnectedDataSourceInterface $connectedDataSource, array $mappedFields);
}