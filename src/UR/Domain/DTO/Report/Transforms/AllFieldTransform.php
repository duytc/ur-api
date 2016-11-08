<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AllFieldTransform
{
    const GROUP_BY_TRANSFORM = 3;
    const SORT_BY_TRANSFORM = 4;

    protected $type;
}