<?php


namespace UR\Domain\DTO\Report\Transforms;


interface AbstractTransformInterface
{
    /**
     * @return int
     */
    public function getTarget();
}