<?php


namespace UR\Model\Core;

class ReportViewTemplate implements ReportViewTemplateInterface
{
    /** @var  int */
    protected $id;

    /** @var  string */
    protected $name;

    /** @var  bool */
    protected $multiView;

    /** @var  array */
    protected $dataSets;

    /** @var  array */
    protected $reportViews;

    /** @var  array */
    protected $joinConfig;

    /** @var  array */
    protected $transforms;

    /** @var  array */
    protected $formats;

    /** @var  array */
    protected $showInTotal;

    /** @var  bool */
    protected $showDataSetName;

    /** @var  array */
    protected $dimensions;

    /** @var  array */
    protected $metrics;

    /** @var  ReportViewTemplateTagInterface[] */
    protected $reportViewTemplateTags;

    /**
     * ReportViewTemplate constructor.
     */
    public function __construct()
    {
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
    public function setId($id)
    {
        $this->id = $id;
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
    public function getJoinConfig()
    {
        return $this->joinConfig;
    }

    /**
     * @inheritdoc
     */
    public function setJoinConfig($joinConfig)
    {
        $this->joinConfig = $joinConfig;

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
     * @return boolean
     */
    public function isShowDataSetName()
    {
        return $this->showDataSetName;
    }

    /**
     * @inheritdoc
     */
    public function setShowDataSetName($showDataSetName)
    {
        $this->showDataSetName = $showDataSetName;

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
    public function getReportViewTemplateTags()
    {
        return $this->reportViewTemplateTags;
    }

    /**
     * @inheritdoc
     */
    public function setReportViewTemplateTags($reportViewTemplateTags)
    {
        $this->reportViewTemplateTags = $reportViewTemplateTags;

        return $this;
    }
}