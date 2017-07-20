<?php


namespace UR\Service\DataSet;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\DTO\Collection;

interface DataMappingServiceInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @param array $params
     * @return mixed
     */
    public function mapTags(DataSetInterface $dataSet, array $params);

    /**
     * @param MapBuilderConfigInterface $config
     * @param Collection $collection
     * @return mixed
     */
    public function importDataFromComponentDataSet(MapBuilderConfigInterface $config, Collection $collection);

    /**
     * Check MapBuilderConfigs are correct or not
     * @param DataSetInterface $dataSet
     * @return bool
     */
    public function validateMapBuilderConfigs(DataSetInterface $dataSet);
}