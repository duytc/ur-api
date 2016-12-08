<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Service\DTO\Report\WeightedCalculationInterface;


class Params implements ParamsInterface
{
    /**
     * @var  DataSetInterface[]
     */
    protected $dataSets;

    /**
     * @var array
     */
    protected $fieldTypes;

    /**
     * @var TransformInterface[]
     */
    protected $transforms;

    /**
     * @var WeightedCalculationInterface[]
     */
    protected $weightedCalculations;

    /**
     * @var null|string
     */
    protected $joinByFields;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var array
     */
    protected $reportViews;

    /**
     * @var boolean
     */
    protected $multiView;

    /**
     * @var array
     */
    protected $showInTotal;

    /**
     * @var FormatInterface[]
     */
    protected $formats;

    /**
     * @var boolean
     */
    protected $subReportIncluded;

    function __construct()
    {
        $this->dataSets = [];
        $this->dataSetTypes = [];
        $this->joinByFields = null;
        $this->transforms = [];
    }

    /**
     * @inheritdoc
     */
    public function getDataSets()
    {
        if (empty($this->dataSets)) {
            return [];
        }

        return $this->dataSets;
    }

    /**
     * @param DataSets\DataSetInterface[] $dataSets
     * @return self
     */
    public function setDataSets($dataSets)
    {
        $this->dataSets = $dataSets;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJoinByFields()
    {
        return $this->joinByFields;
    }

    /**
     * @param null $joinByFields
     * @return self
     */
    public function setJoinByFields($joinByFields)
    {
        $this->joinByFields = $joinByFields;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        if (empty($this->transforms)) {
            return [];
        }

        return $this->transforms;
    }

    /**
     * @param array $transforms
     * @return self
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;

        return $this;
    }

    /**
     * @return WeightedCalculationInterface
     */
    public function getWeightedCalculations()
    {
        return $this->weightedCalculations;
    }

    /**
     * @param WeightedCalculationInterface $weightedCalculations
     * @return self
     */
    public function setWeightedCalculations($weightedCalculations)
    {
        $this->weightedCalculations = $weightedCalculations;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     * @return self
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return array
     */
    public function getReportViews()
    {
        return $this->reportViews;
    }

    /**
     * @param array $reportViews
     * @return self
     */
    public function setReportViews($reportViews)
    {
        $this->reportViews = $reportViews;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isMultiView()
    {
        return $this->multiView;
    }

    /**
     * @param boolean $multiView
     * @return self
     */
    public function setMultiView($multiView)
    {
        $this->multiView = $multiView;
        return $this;
    }

    /**
     * @return array|bool
     */
    public function getSortByFields()
    {
        $transforms = $this->getTransforms();
        if (empty($transforms)) {
            return false;
        }

        $sortByTransforms = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof SortByTransformInterface) {
                $sortByTransforms [] = $transform ;
            }
        }

        return $sortByTransforms;
    }

    /**
     * @return array
     */
    public function getShowInTotal()
    {
        return $this->showInTotal;
    }

    /**
     * @param array $showInTotal
     * @return self
     */
    public function setShowInTotal($showInTotal)
    {
        $this->showInTotal = $showInTotal;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * @inheritdoc
     */
    public function setFormats($formats)
    {
        $this->formats = $formats;
        return $this;
    }

    /**
     * @return array
     */
    public function getFieldTypes()
    {
        return $this->fieldTypes;
    }

    /**
     * @param array $fieldTypes
     * @return self
     */
    public function setFieldTypes($fieldTypes)
    {
        $this->fieldTypes = $fieldTypes;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isSubReportIncluded()
    {
        return $this->subReportIncluded;
    }

    /**
     * @param boolean $subReportIncluded
     * @return self
     */
    public function setSubReportIncluded($subReportIncluded)
    {
        $this->subReportIncluded = $subReportIncluded;
        return $this;
    }
}