<?php


namespace UR\Domain\DTO\Report\Transforms;


interface GroupByTransformInterface extends TransformInterface
{
    /**
     * @return array
     */
    public function getFields();
}