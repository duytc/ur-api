<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Service\DTO\Report\WeightedCalculationInterface;

interface ParamsInterface
{
    /**
     * @return TransformInterface[]
     */
    public function getTransforms();

    public function addTransform(TransformInterface $transform);

    public function setTransforms($transforms);

    /**
     * @return DataSet[]
     */
    public function getDataSets();

    /**
     * @return array
     */
    public function getJoinConfigs();

    /**
     * @param $joinConfigs
     * @return $this
     */
    public function setJoinConfigs(array $joinConfigs);

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

    /**
     * @return mixed
     */
    public function getReportViewId();

    /**
     * @param mixed $reportViewId
     */
    public function setReportViewId($reportViewId);

    /**
     * @return mixed
     */
    public function getPage();

    /**
     * @param mixed $page
     * @return self
     */
    public function setPage($page);

    /**
     * @return mixed
     */
    public function getLimit();

    /**
     * @param mixed $limit
     * @return self
     */
    public function setLimit($limit);

    /**
     * @return mixed
     */
    public function getOrderBy();

    /**
     * @param mixed $orderBy
     * @return self
     */
    public function setOrderBy($orderBy);

    /**
     * @return mixed
     */
    public function getSortField();

    /**
     * @param mixed $sortField
     * @return self
     */
    public function setSortField($sortField);

    /**
     * @return array
     */
    public function getSearches();

    /**
     * @param array $searches
     * @return self
     */
    public function setSearches($searches);

    /**
     * @return array
     */
    public function getUserDefinedDimensions();

    /**
     * @param array $userDefinedDimensions
     * @return self
     */
    public function setUserDefinedDimensions($userDefinedDimensions);

    /**
     * @return array
     */
    public function getUserDefinedMetrics();

    /**
     * @param array $userDefinedMetrics
     * @return self
     */
    public function setUserDefinedMetrics($userDefinedMetrics);

    /**
     * @return array
     */
    public function getDimensions();

    /**
     * @param array $dimensions
     * @return self
     */
    public function setDimensions($dimensions);

    /**
     * @return array
     */
    public function getMetrics();

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics($metrics);

    /**
     * @return array
     */
    public function getMetricCalculations();

    /**
     * @param array $metricCalculations
     * @return self
     */
    public function setMetricCalculations($metricCalculations);

    /**
     * @return boolean
     */
    public function isUserProvidedDimensionEnabled();

    /**
     * @param boolean $userProvidedDimensionEnabled
     * @return self
     */
    public function setUserProvidedDimensionEnabled($userProvidedDimensionEnabled);

    /**
     * @return boolean
     */
    public function isSubView();

    /**
     * @param boolean $subview
     * @return self
     */
    public function setSubView($subview);

    /**
     * @return array
     */
    public function getMagicMaps();

    /**
     * @param array $magicMaps
     */
    public function setMagicMaps($magicMaps);

    /**
     * @return string
     */
    public function getTemporarySuffix();

    /**
     * @param string $temporarySuffix
     */
    public function setTemporarySuffix($temporarySuffix);

    /**
     * @return mixed
     */
    public function getReportView();

    /**
     * @param mixed $reportView
     * @return self
     */
    public function setReportView($reportView);

    /**
     * @return bool
     */
    public function isNeedFormat();

    /**
     * @param bool $needFormat
     * @return self
     */
    public function setNeedFormat($needFormat);

    /**
     * @return boolean
     */
    public function isOptimizationRule();

    /**
     * @param boolean $optimizationRule
     * @return self
     */
    public function setOptimizationRule($optimizationRule);
}