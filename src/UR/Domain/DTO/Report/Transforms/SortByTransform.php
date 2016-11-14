<?php


namespace UR\Domain\DTO\Report\Transforms;


class SortByTransform extends AllFieldTransform implements SortByTransformInterface
{
    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    /**
     * @var array
     */
    protected $fields;

    protected $direction;

    function __construct($fields, $direction, $type, $target)
    {
        parent::__construct($type, $target);
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
}