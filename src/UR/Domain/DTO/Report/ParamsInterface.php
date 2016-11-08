<?php


namespace UR\Domain\DTO\Report;


use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
use UR\Domain\DTO\Report\Transforms\AbstractTransformInterface;
use UR\Model\Core\DataSetInterface;

interface ParamsInterface
{
    /**
     * @return boolean
     */
    public function needToGroup();

    /**
     * @return AbstractFilterInterface[]
     */
    public function getFilters();

    /**
     * @return AbstractTransformInterface
     */
    public function getTransforms();

    /**
     * @return DataSetInterface[]
     */
    public function getDataSets();
}