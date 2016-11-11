<?php


namespace UR\Domain\DTO\Report\Transforms;


class SortByTransform extends AllFieldTransform implements SortByTransformInterface
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