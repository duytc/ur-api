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

    /**
     * @return array
     */
    public function getFilters();

    /**
     * @param array $filters
     * @return self
     */
    public function setFilters($filters);

    /**
     * @return array
     */
    public function getReportViews();

    /**
     * @param array $reportViews
     * @return self
     */
    public function setReportViews($reportViews);

    /**
     * @return boolean
     */
    public function isMultiView();

    /**
     * @param boolean $multiView
     * @return self
     */
    public function setMultiView($multiView);
}