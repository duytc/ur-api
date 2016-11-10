<?php


namespace UR\Domain\DTO\Report\Transforms;


class GroupByTransform extends AllFieldTransform implements GroupByTransformInterface
{
    /**
     * @var array
     */
    protected $fields;

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }
}