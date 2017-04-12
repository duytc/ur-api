<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform
{
	const TRANSFORMS_TYPE = 'abstractTransform';

    /**
     * AbstractTransform constructor.
     */
    public function __construct()
    {
    }

    public function getTransformsType()
    {
	    return self::TRANSFORMS_TYPE;
    }
}