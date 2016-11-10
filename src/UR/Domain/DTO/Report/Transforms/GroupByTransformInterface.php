<?php


namespace UR\Domain\DTO\Report\Transforms;


interface GroupByTransformInterface extends AllFiledTransformInterface
{
    /**
     * @return array
     */
    public function getFields();
}