<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform
{
    const PRIORITY = null;

    protected $priority;

    /**
     * AbstractTransform constructor.
     */
    public function __construct()
    {
        $this->priority = static::PRIORITY;
    }

    /**
     * @return mixed
     */
    public function getPriority()
    {
        return $this->priority;
    }
}