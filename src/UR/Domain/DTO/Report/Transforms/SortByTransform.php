<?php


namespace UR\Domain\DTO\Report\Transforms;


class SortByTransform extends AllFieldTransform implements SortByTransformInterface
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