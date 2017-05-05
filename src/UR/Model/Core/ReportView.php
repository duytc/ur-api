<?php


namespace UR\Model\Core;


use Doctrine\Common\Collections\ArrayCollection;
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
     * @var \DateTime
     */
    protected $createdDate;

    /**
     * @var array
     */
    protected $weightedCalculations;

    /**
     * @var boolean
     */
    protected $multiView;

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

    protected $reportViewMultiViews;

    /**
     * @var array
     */
    protected $reportViewDataSets;

    /**
     * @var bool
     */
    protected $isShowDataSetName;

    protected $lastActivity;

    protected $lastRun;

    /**
     * @var bool
     */
    protected $enableCustomDimensionMetric;

    /**
     * @return mixed
     */
    public function getShared()
    {
        return count($this->getSharedKeysConfig()) > 0;
    }

    /**
     * ReportView constructor.
     */
    public function __construct()
    {
        $this->multiView = false;
        $this->sharedKeysConfig = [];
        $this->joinBy = [];
        $this->reportViewDataSets = new ArrayCollection();
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
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

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
        return bin2hex(random_bytes(15));
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

    /**
     * @return ReportViewMultiViewInterface[]
     */
    public function getReportViewMultiViews()
    {
        return $this->reportViewMultiViews;
    }

    /**
     * @param mixed $reportViewMultiViews
     * @return self
     */
    public function setReportViewMultiViews($reportViewMultiViews)
    {
        $this->reportViewMultiViews = $reportViewMultiViews;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReportViewDataSets()
    {
        return $this->reportViewDataSets;
    }

    /**
     * @param mixed $reportViewDataSets
     * @return self
     */
    public function setReportViewDataSets($reportViewDataSets)
    {
        $this->reportViewDataSets = $reportViewDataSets;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsShowDataSetName()
    {
        return $this->isShowDataSetName;
    }

    /**
     * @inheritdoc
     */
    public function setIsShowDataSetName($isShowDataSetName)
    {
        $this->isShowDataSetName = $isShowDataSetName;
    }

    /**
     * @inheritdoc
     */
    public function getLastActivity()
    {
        return $this->lastActivity;
    }

    /**
     * @inheritdoc
     */
    public function setLastActivity($lastActivity)
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLastRun()
    {
        return $this->lastRun;
    }

    /**
     * @inheritdoc
     */
    public function setLastRun($lastRun)
    {
        $this->lastRun = $lastRun;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isEnableCustomDimensionMetric()
    {
        return $this->enableCustomDimensionMetric;
    }

    /**
     * @inheritdoc
     */
    public function setEnableCustomDimensionMetric($enableCustomDimensionMetric)
    {
        $this->enableCustomDimensionMetric = $enableCustomDimensionMetric;
        return $this;
    }
}