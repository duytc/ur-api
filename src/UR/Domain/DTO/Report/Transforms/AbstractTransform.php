<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform
{
    const PRIORITY = null;
	const TRANSFORMS_TYPE = 'abstractTransform';

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

    public function getTransformsType()
    {
	    return self::TRANSFORMS_TYPE;
    }
}