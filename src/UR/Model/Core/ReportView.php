<?php


namespace UR\Model\Core;


use UR\Model\User\Role\PublisherInterface;

class ReportView implements ReportViewInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $dataSets;

    /**
     * @var string
     */
    protected $joinBy;

    /**
     * @var array
     */
    protected $transforms;

    /**
     * @var array
     */
    protected $weightedCalculations;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var boolean
     */
    protected $multiView;

    /**
     * @var array
     */
    protected $reportViews;

    /**
     * @var array
     */
    protected $metrics;

    /**
     * @var array
     */
    protected $fieldTypes;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * @var string
     */
    protected $sharedKey;

    /**
     * @var array
     */
    protected $showInTotal;

    /**
     * @var array
     */
    protected $formats;

    /**
     * @var boolean
     */
    protected $subReportsIncluded;

    /**
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * ReportView constructor.
     */
    public function __construct()
    {
        $this->multiView = false;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return array
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }

    /**
     * @param array $dataSets
     * @return self
     */
    public function setDataSets($dataSets)
    {
        $this->dataSets = $dataSets;
        return $this;
    }

    /**
     * @return string
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @param string $joinBy
     * @return self
     */
    public function setJoinBy($joinBy)
    {
        $this->joinBy = $joinBy;
        return $this;
    }

    /**
     * @return array
     */
    public function getTransforms()
    {
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
     * @return array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
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
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @param array $dimensions
     * @return self
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;
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
     * @return PublisherInterface
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getWeightedCalculations()
    {
        return $this->weightedCalculations;
    }

    /**
     * @inheritdoc
     */
    public function setWeightedCalculations($weightedCalculations)
    {
        $this->weightedCalculations = $weightedCalculations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSharedKey()
    {
        return $this->sharedKey;
    }

    /**
     * @inheritdoc
     */
    public function setSharedKey($sharedKey)
    {
        $this->sharedKey = $sharedKey;
        return $this;
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
     * Use this if need generate shared key automatically
     * @return self
     */
    public static function generateSharedKey()
    {
        return str_replace(".", "", uniqid(rand(1, 10000), true));
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
     * @inheritdoc
     */
    public function isSubReportsIncluded()
    {
        return $this->subReportsIncluded;
    }

    /**
     * @inheritdoc
     */
    public function setSubReportsIncluded($subReportsIncluded)
    {
        $this->subReportsIncluded = $subReportsIncluded;
        return $this;
    }
}