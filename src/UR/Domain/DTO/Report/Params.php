<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
use UR\Domain\DTO\Report\Transforms\AbstractTransformInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Model\Core\DataSetInterface;

class Params implements ParamsInterface
{
    /**
     * @var AbstractFilterInterface[]
     */
    protected $filters;

    /**
     * @var AbstractTransformInterface
     */
    protected $transforms;

    /**
     * @var DataSetInterface[]
     */
    protected $dataSets;

    public function needToGroup()
    {
        if (empty($this->getTransforms())) {
            return false;
        }

        foreach($this->getTransforms() as $transform) {
            if ($transform instanceof GroupByTransform) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return AbstractFilterInterface[]
     */
    public function getFilters()
    {
        if (empty($this->filters)) {
            return [];
        }

        return $this->filters;
    }

    /**
     * @return AbstractTransformInterface[]
     */
    public function getTransforms()
    {
        if (empty($this->transforms)) {
            return [];
        }

        return $this->transforms;
    }

    /**
     * @return DataSetInterface[]
     */
    public function getDataSets()
    {
        if (empty($this->dataSets)) {
            return [];
        }

        return $this->dataSets;
    }
}