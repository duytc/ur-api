<?php

namespace UR\Service\Parser\Filter;


class AbstractFilter
{
    private $field;

    /**
     * AbstractFilter constructor.
     * @param $field
     */
    public function __construct($field)
    {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param mixed $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }
}