<?php


namespace UR\Model\Core;


class ReportViewDataSet implements ReportViewDataSetInterface
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
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * @var array
     */
    protected $metrics;

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
     * @return DataSetInterface
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
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
     * @return mixed
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @param mixed $metrics
     * @return self
     */
    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
        return $this;
    }
    /**
     * @param $id
     */
    public function setId($id) {
        $this->id = $id;
    }
}