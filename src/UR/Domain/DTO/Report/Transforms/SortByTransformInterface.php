<?php


namespace UR\Domain\DTO\Report\Transforms;


interface SortByTransformInterface extends AllFiledTransformInterface
{
    /**
     * @return array
     */
    public function getFields();
}