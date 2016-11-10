<?php


namespace UR\Domain\DTO\Report\Transforms;


interface AllFiledTransformInterface extends AbstractTransformInterface
{
    /**
     * @return int
     */
    public function getType();
}