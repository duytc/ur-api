<?php


namespace UR\Model\Core;


class AutoOptimizationConfigDataSet implements AutoOptimizationConfigDataSetInterface
{
    protected $id;
    protected $filters;
    protected $dimensions;
    protected $metrics;
    protected $autoOptimizationConfig;
    protected $dataSet;

    public function __construct()
    {
        $this->filters = [];
        $this->dimensions = [];
        $this->metrics = [];
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
    }

    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfig()
    {
        return $this->autoOptimizationConfig;
    }

    /**
     * @inheritdoc
     */
    public function setAutoOptimizationConfig($autoOptimizationConfig)
    {
        $this->autoOptimizationConfig = $autoOptimizationConfig;
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @inheritdoc
     */
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
    }
}