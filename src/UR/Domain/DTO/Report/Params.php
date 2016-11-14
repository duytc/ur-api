<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;


class Params implements ParamsInterface
{
    /**
     * @var  DataSetInterface[]
     */
    protected $dataSets;

    /**
     * @var TransformInterface[]
     */
    protected $transforms;

    /**
     * @var null|string
     */
    protected $joinByFields;

    function __construct()
    {
        $this->dataSets = [];
        $this->joinByFields = null;
        $this->transforms = [];
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

    /**
     * @param DataSets\DataSetInterface[] $dataSets
     * @return self
     */
    public function setDataSets($dataSets)
    {
        $this->dataSets = $dataSets;
        return $this;
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
     * @param null $joinByFields
     * @return self
     */
    public function setJoinByFields($joinByFields)
    {
        $this->joinByFields = $joinByFields;
        return $this;
    }


    /**
     * @inheritdoc
     */
    public function getGroupByTransform()
    {
        if (empty($this->getTransforms())) {
            return false;
        }

        foreach ($this->getTransforms() as $transform) {
            if ($transform instanceof GroupByTransformInterface) {
                return $transform;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        if (empty($this->transforms)) {
            return [];
        }

        return $this->transforms;
    }

    /**
     * @param array $transforms
     * @return self
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
        return $this;
    }
}