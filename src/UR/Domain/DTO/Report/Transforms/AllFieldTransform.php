<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class AllFieldTransform extends AbstractTransform
{
    const GROUP_BY_TRANSFORM = 3;
    const SORT_BY_TRANSFORM = 4;


    /**
     * @var int
     */
    protected $type;

    function __construct($type, $target)
    {
        parent::__construct($target);
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }
}