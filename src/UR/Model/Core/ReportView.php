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
     * @var string
     */
    protected $alias;

    /**
     * @var array
     */
    protected $dataSets;

    /**
     * formatted as
     * [
     *      {
     *          "joinFields":[
     *              {
     *                  "dataSet":1,
     *                  "field":"a"
     *              },
     *              {
     *                  "dataSet":2,
     *                  "field":"b"
     *              }
     *          ],
     *          "outputField":"ab"
     *      }
     * ]
     * @var array
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
     * @var array format as
     * [
     *      token1 => [field1, field2, ...],
     *      token2 => [field1, field3, ...],
     *      ...
     * ]
     */
    protected $sharedKeysConfig;

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
        $this->sharedKeysConfig = [];
        $this->joinBy = [];
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @inheritdoc
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }

    /**
     * @inheritdoc
     */
    public function setDataSets($dataSets)
    {
        $this->dataSets = $dataSets;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @inheritdoc
     */
    public function setJoinBy(array $joinBy)
    {
        $this->joinBy = $joinBy;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @inheritdoc
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @inheritdoc
     */
    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFieldTypes()
    {
        return $this->fieldTypes;
    }

    /**
     * @inheritdoc
     */
    public function setFieldTypes($fieldTypes)
    {
        $this->fieldTypes = $fieldTypes;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @inheritdoc
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMultiView()
    {
        return $this->multiView;
    }

    /**
     * @inheritdoc
     */
    public function setMultiView($multiView)
    {
        $this->multiView = $multiView;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReportViews()
    {
        return $this->reportViews;
    }

    /**
     * @inheritdoc
     */
    public function setReportViews($reportViews)
    {
        $this->reportViews = $reportViews;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
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
    public function getSharedKeysConfig()
    {
        return $this->sharedKeysConfig;
    }

    /**
     * @inheritdoc
     */
    public function setSharedKeysConfig($sharedKeysConfig)
    {
        $this->sharedKeysConfig = $sharedKeysConfig;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getShowInTotal()
    {
        return $this->showInTotal;
    }

    /**
     * @inheritdoc
     */
    public function setShowInTotal($showInTotal)
    {
        $this->showInTotal = $showInTotal;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function generateToken()
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