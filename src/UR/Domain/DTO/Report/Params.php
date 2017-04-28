<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
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
     * @var array
     * TODO: use UR\Domain\DTO\Report\JoinBy\JoinByInterface instead
     */
    protected $joinConfigs;

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

    protected $startDate;
    protected $endDate;
    protected $reportViewId;
    protected $page;
    protected $limit;
    protected $searchField;
    protected $searchKey;
    protected $orderBy;
    protected $sortField;

    /**
     * @var array
     */
    protected $searches;

    /**
     * @var boolean
     */
    protected $isShowDataSetName;

    function __construct()
    {
        $this->dataSets = [];
        $this->fieldTypes = [];
        $this->joinConfigs = [];
        $this->transforms = [];
        $this->searches = [];
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
    public function getJoinConfigs()
    {
        return $this->joinConfigs;
    }

    /**
     * @param array $joinConfigs
     * @return self
     */
    public function setJoinConfigs(array $joinConfigs)
    {
        $this->joinConfigs = $joinConfigs;

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
            if ($transform instanceof SortByTransform) {
                $sortByTransforms [] = $transform;
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

    /**
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param mixed $startDate
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param mixed $endDate
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * @return boolean
     */
    public function getIsShowDataSetName()
    {
        return $this->isShowDataSetName;
    }

    /**
     * @param boolean $isShowDataSetName
     * @return self
     */
    public function setIsShowDataSetName($isShowDataSetName)
    {
        $this->isShowDataSetName = $isShowDataSetName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReportViewId()
    {
        return $this->reportViewId;
    }

    /**
     * @param mixed $reportViewId
     */
    public function setReportViewId($reportViewId)
    {
        $this->reportViewId = $reportViewId;
    }

    /**
     * @return mixed
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param mixed $page
     * @return self
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param mixed $limit
     * @return self
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSearchField()
    {
        return $this->searchField;
    }

    /**
     * @param mixed $searchField
     * @return self
     */
    public function setSearchField($searchField)
    {
        $this->searchField = $searchField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSearchKey()
    {
        return $this->searchKey;
    }

    /**
     * @param mixed $searchKey
     * @return self
     */
    public function setSearchKey($searchKey)
    {
        $this->searchKey = $searchKey;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * @param mixed $orderBy
     * @return self
     */
    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * @param mixed $sortField
     * @return self
     */
    public function setSortField($sortField)
    {
        $this->sortField = $sortField;
        return $this;
    }

    /**
     * @return array
     */
    public function getSearches()
    {
        return $this->searches;
    }

    /**
     * @param array $searches
     * @return self
     */
    public function setSearches($searches)
    {
        $this->searches = $searches;
        return $this;
    }
}