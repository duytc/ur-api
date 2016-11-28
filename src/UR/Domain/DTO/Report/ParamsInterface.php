<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\JoinBy\JoinByInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Service\DTO\Report\WeightedCalculationInterface;

interface ParamsInterface
{
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
     * @return WeightedCalculationInterface
     */
    public function getWeightedCalculations();
}