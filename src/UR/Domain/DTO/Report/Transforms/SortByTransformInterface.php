<?php


namespace UR\Domain\DTO\Report\Transforms;


interface SortByTransformInterface extends TransformInterface
{
    /**
     * @return array
     */
    public function getAscSorts();

    /**
     * @return array
     */
    public function getDescSorts();
}