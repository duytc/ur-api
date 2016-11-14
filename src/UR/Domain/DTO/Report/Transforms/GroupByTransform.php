<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

class GroupByTransform implements GroupByTransformInterface
{
    /**
     * @var array
     */
    protected $fields;

    function __construct($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function transform(Collection $collection)
    {
        // TODO: Implement transform() method.
    }
}