<?php


namespace UR\Domain\DTO\Report\Transforms;


interface SingleFieldTransformInterface
{
    /**
     * @return int
     */
    public function getType();

    /**
     * @return string
     */
    public function getFieldName();
}