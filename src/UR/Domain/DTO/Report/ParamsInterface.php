<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
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
     * @return array
     */
    public function getJoinConfigs();

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

    /**
     * @return array
     */
    public function getShowInTotal();

    /**
     * @param array $showInTotal
     * @return self
     */
    public function setShowInTotal($showInTotal);

    /**
     * @return null|array|FormatInterface[]
     */
    public function getFormats();

    /**
     * @param null|array|FormatInterface[] $formats
     * @return self
     */
    public function setFormats($formats);

    /**
     * @return array
     */
    public function getFieldTypes();

    /**
     * @param array $dataSetTypes
     * @return self
     */
    public function setFieldTypes($dataSetTypes);

    /**
     * @return boolean
     */
    public function isSubReportIncluded();

    /**
     * @param boolean $subReportIncluded
     * @return self
     */
    public function setSubReportIncluded($subReportIncluded);

	/**
	 * @return mixed
	 */
	public function getStartDate();

	/**
	 * @param mixed $startDate
	 */
	public function setStartDate($startDate);

	/**
	 * @return mixed
	 */
	public function getEndDate();

	/**
	 * @param mixed $endDate
	 */
	public function setEndDate($endDate);

    /**
     * @return boolean
     */
    public function getIsShowDataSetName();

    /**
     * @param boolean $isShowDataSetName
     * @return self
     */
    public function setIsShowDataSetName($isShowDataSetName);
}