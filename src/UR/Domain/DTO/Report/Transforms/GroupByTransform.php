<?php


namespace UR\Domain\DTO\Report\Transforms;


class GroupByTransform extends AllFieldTransform implements GroupByTransformInterface
{
    /**
     * @var array
     */
    protected $fields;

    function __construct($fields, $type, $target)
    {
        parent::__construct($type, $target);
        $this->fields = $fields;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }
}