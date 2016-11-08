<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform implements AbstractTransformInterface
{
    const SINGLE_FIELD_TARGET = 1;
    const ALL_FIELD_TARGET = 2;

    protected $target;
}