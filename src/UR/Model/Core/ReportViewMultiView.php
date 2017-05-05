<?php


namespace UR\Model\Core;


class ReportViewMultiView implements ReportViewMultiViewInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ReportViewInterface
     */
    protected $reportView;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var ReportViewInterface
     */
    protected $subView;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * @var array
     */
    protected $metrics;

    /**
     * @var bool
     */
    protected $enableCustomDimensionMetric;

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ReportViewInterface
     */
    public function getReportView()
    {
        return $this->reportView;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return self
     */
    public function setReportView($reportView)
    {
        $this->reportView = $reportView;
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
     * @return ReportViewInterface
     */
    public function getSubView()
    {
        return $this->subView;
    }

    /**
     * @param ReportViewInterface $subView
     * @return self
     */
    public function setSubView($subView)
    {
        $this->subView = $subView;
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
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics(array  $metrics)
    {
        $this->metrics = $metrics;
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