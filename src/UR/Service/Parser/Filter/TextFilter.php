<?php

namespace UR\Service\Parser\Filter;

class TextFilter implements ColumnFilterInterface
{
    protected $comparison;
    protected $compareValue;

    public function __construct($comparison, $compareValue)
    {
        $this->comparison = $comparison;
        $this->compareValue = $compareValue;
    }

    public function filter($filter)
    {
        // TODO: Implement filter() method.
    }
}