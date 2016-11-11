<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;


class Params implements ParamsInterface
{
    /** @var  DataSetInterface[] $dataSets */
    protected $dataSets;
    protected $transformations;
    protected $joinByFields;

    function __construct($dataSets, $joinByFields, $transformations)
    {
        $this->dataSets = $dataSets;
        $this->joinByFields = $joinByFields;
        $this->transformations = $transformations;
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

    /** @inheritdoc */
    public function getFiltersByDataSet($dataSetId)
    {
        if (!is_array($this->dataSets)) {
            throw new \Exception(sprintf('Expect dataSet is object'));
        }

        foreach ($this->dataSets as $dataSet) {
            if ($dataSet->getDataSetId() == $dataSetId) {
                return $dataSet->getFilters();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getMetricsByDataSet($dataSetId)
    {
        if (!is_array($this->dataSets)) {
            throw new \Exception(sprintf('Expect dataSet is object'));
        }

        foreach ($this->dataSets as $dataSet) {
            if ($dataSet->getDataSetId() == $dataSetId) {
                return $dataSet->getMetrics();
            }
        }

    }

    /**
     * @inheritdoc
     */
    public function getDimensionByDataSet($dataSetId)
    {
        if (!is_array($this->dataSets)) {
            throw new \Exception(sprintf('Expect dataSet is object'));
        }

        foreach ($this->dataSets as $dataSet) {
            if ($dataSet->getDataSetId() == $dataSetId) {
                return $dataSet->getDimensions();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getJoinByFields()
    {
        return $this->joinByFields;
    }

    /**
     * @inheritdoc
     */
    public function needToGroup()
    {
        if (empty($this->getTransformations())) {
            return false;
        }

        foreach ($this->getTransformations() as $transform) {
            if ($transform instanceof GroupByTransform) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTransformations()
    {
        if (empty($this->transformations)) {
            return [];
        }

        return $this->transformations;
    }

}