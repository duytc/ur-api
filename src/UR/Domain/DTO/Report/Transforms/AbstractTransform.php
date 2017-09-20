<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AbstractTransform
{
	const TRANSFORMS_TYPE = 'abstractTransform';
	private $isPostGroup;

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

	/**
	 * @return mixed
	 */
	public function getIsPostGroup()
	{
		return $this->isPostGroup;
	}

	/**
	 * @param mixed $isPostGroup
	 */
	public function setIsPostGroup($isPostGroup)
	{
		$this->isPostGroup = $isPostGroup;
	}
}