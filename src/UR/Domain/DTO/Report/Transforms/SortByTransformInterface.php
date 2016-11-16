<?php


namespace UR\Domain\DTO\Report\Transforms;


interface SortByTransformInterface extends TransformInterface
{
    /**
     * @return array
     */
    public function getFields();

    /**
     * @return string
     */
    public function getDirection();
}