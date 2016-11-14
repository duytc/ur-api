<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

class SortByTransform implements SortByTransformInterface
{
    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    /**
     * @var array
     */
    protected $fields;

    protected $direction;

    function __construct($fields, $direction)
    {
        $this->fields = $fields;
        $this->direction = $direction;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return mixed
     */
    public function getDirection()
    {
        return $this->direction;
    }

    public function transform(Collection $collection)
    {
        // TODO: Implement transform() method.
    }
}