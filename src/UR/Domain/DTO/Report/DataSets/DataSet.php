<?php


namespace UR\Domain\DTO\Report\DataSets;


class DataSet implements DataSetInterface
{
    protected $dataSetName;
    protected $dimensions;
    protected $metrics;
    protected $filters;

    function __construct($dataSetName, $dimensions, $filters, $metrics)
    {
        $this->dimensions = $dimensions;
        $this->filters = $filters;
        $this->metrics = $metrics;
        $this->dataSetName = $dataSetName;
    }

    /**
     * @inheritdoc
     */
    public function getDimensions()
    {
        // TODO: Implement getDimensions() method.
    }

    /**
     * @inheritdoc
     */
    public function getMetrics()
    {
        // TODO: Implement getMetrics() method.
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        // TODO: Implement getFilters() method.
    }

    /**
     * @inheritdoc
     */
    public function getDataSetName()
    {
        return $this->dataSetName;
    }
}