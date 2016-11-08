<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
use UR\Domain\DTO\Report\Transforms\AbstractTransformInterface;
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
        // TODO: Implement needToGroup() method.
    }

    /**
     * @return AbstractFilterInterface[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @return AbstractTransformInterface
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @return DataSetInterface[]
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }
}