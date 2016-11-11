<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform implements AbstractTransformInterface
{
    const SINGLE_FIELD_TARGET = 1;
    const ALL_FIELD_TARGET = 2;

    /**
     * @var int
     */
    protected $target;

    function __construct($target)
    {
        $this->target = $target;
    }

    /**
     * @return mixed
     */
    public function getTarget()
    {
        return $this->target;
    }
}