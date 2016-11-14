<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;


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

        return [];
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

        return [];
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
    public function getGroupByTransform()
    {
        if (empty($this->getTransformations())) {
            return false;
        }

        foreach ($this->getTransformations() as $transform) {
            if ($transform instanceof GroupByTransformInterface) {
                return $transform;
            }
        }

        return false;
    }

    public function getSortByTransform()
    {
        if (empty($this->getTransformations())) {
            return false;
        }

        foreach ($this->getTransformations() as $transform) {
            if ($transform instanceof SortByTransformInterface) {
                return $transform;
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