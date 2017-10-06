<?php


namespace UR\Service\DataSet;


use UR\Model\Core\ConnectedDataSourceInterface;

interface TransformOrdersServiceInterface
{
    /**
     * @param array $transforms
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param int $index
     * @return mixed
     */
    public function orderTransforms(array $transforms, ConnectedDataSourceInterface $connectedDataSource, $index = 0);
}