<?php


namespace UR\Model\Core;


use DateTime;
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
     * @var bool
     */
    protected $enableCustomDimensionMetric;

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
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * @var array
     */
    protected $reportViewDataSets;

    /**
     * @var bool
     */
    protected $isShowDataSetName;

    /**
     * @var DateTime
     */
    protected $lastActivity;

    /**
     * @var DateTime
     */
    protected $lastRun;

    /**
     * @var ReportViewInterface
     */
    protected $masterReportView;

    /**
     * @var ReportViewInterface[]
     */
    protected $subReportViews;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var bool
     */
    protected $subView;

    /** @var  bool */
    protected $largeReport;

    /** @var  bool */
    protected $availableToRun;

    /** @var  bool */
    protected $availableToChange;

    /** @var  string */
    protected $preCalculateTable;

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
        $this->sharedKeysConfig = [];
        $this->joinBy = [];
        $this->reportViewDataSets = new ArrayCollection();
        $this->enableCustomDimensionMetric = true;
        $this->subView = false;
        $this->filters = [];

        /** Setup for large report */
        $this->largeReport = false;
        $this->availableToRun = true;
        $this->availableToChange = true;
        $this->preCalculateTable = null;
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
    public function setId($id) {
        $this->id = $id;

        return $this;
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
     * @return boolean
     */
    public function isEnableCustomDimensionMetric()
    {
        return $this->enableCustomDimensionMetric;
    }

    /**
     * @param boolean $enableCustomDimensionMetric
     * @return self
     */
    public function setEnableCustomDimensionMetric($enableCustomDimensionMetric)
    {
        $this->enableCustomDimensionMetric = $enableCustomDimensionMetric;
        return $this;
    }

    /**
     * @return ReportViewInterface
     */
    public function getMasterReportView()
    {
        return $this->masterReportView;
    }

    /**
     * @param ReportViewInterface $masterReportView
     * @return self
     */
    public function setMasterReportView($masterReportView)
    {
        $this->masterReportView = $masterReportView;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSubReportViews()
    {
        return $this->subReportViews;
    }

    /**
     * @inheritdoc
     */
    public function setSubReportViews($subReportViews)
    {
        $this->subReportViews = $subReportViews;

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
    public function isSubView()
    {
        return $this->subView;
    }

    /**
     * @param boolean $subview
     * @return self
     */
    public function setSubView($subview)
    {
        $this->subView = $subview;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isLargeReport()
    {
        return $this->largeReport;
    }

    /**
     * @inheritdoc
     */
    public function setLargeReport($largeReport)
    {
        $this->largeReport = $largeReport;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isAvailableToRun()
    {
        return $this->availableToRun;
    }

    /**
     * @inheritdoc
     */
    public function setAvailableToRun($availableToRun)
    {
        $this->availableToRun = $availableToRun;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isAvailableToChange()
    {
        return $this->availableToChange;
    }

    /**
     * @inheritdoc
     */
    public function setAvailableToChange($availableToChange)
    {
        $this->availableToChange = $availableToChange;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPreCalculateTable()
    {
        return $this->preCalculateTable;
    }

    /**
     * @inheritdoc
     */
    public function setPreCalculateTable($preCalculateTable)
    {
        $this->preCalculateTable = $preCalculateTable;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSmallReport()
    {
//        $this->setLargeReport(false);
        $this->setPreCalculateTable(null);

        $this->setAvailableToChange(true);
        $this->setAvailableToRun(true);

        return $this;
    }
}