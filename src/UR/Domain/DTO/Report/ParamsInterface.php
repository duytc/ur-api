<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\JoinBy\JoinByInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;

interface ParamsInterface
{
    /**
     * @return boolean|GroupByTransformInterface
     */
    public function getGroupByTransform();

    /**
     * @return TransformInterface[]
     */
    public function getTransforms();

    /**
     * @return DataSet[]
     */
    public function getDataSets();

    /**
     * @return JoinByInterface
     */
    public function getJoinByFields();

    /**
     * @param $dataSetId
     * @return AbstractFilter[];
     */
    public function getFiltersByDataSet($dataSetId);

    /**
     * @param $dataSetId
     * @return array
     */
    public function getMetricsByDataSet($dataSetId);

    /**
     * @param $dataSetId
     * @return array
     */
    public function getDimensionByDataSet($dataSetId);

}