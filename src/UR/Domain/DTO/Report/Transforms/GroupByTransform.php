<?php


namespace UR\Domain\DTO\Report\Transforms;


class GroupByTransform extends AllFieldTransform
{
    protected $fields;

    function __construct($fields, $type)
    {
        parent::__construct($type);
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