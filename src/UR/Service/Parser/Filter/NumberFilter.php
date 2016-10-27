<?php

namespace UR\Service\Parser\Filter;

use UR\Service\DataSet\Comparison;

class NumberFilter implements ColumnFilterInterface
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
        if (strcmp($this->comparison, Comparison::SMALLER) === 0) {

            return $filter < $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, Comparison::SMALLER_OR_EQUAL) === 0) {

            return $filter <= $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, Comparison::EQUAL) === 0) {

            return $this->compareValue === $filter ? true : false;
        }

        if (strcmp($this->comparison, Comparison::NOT_EQUAL) === 0) {

            return $this->compareValue !== $filter ? true : false;
        }

        if (strcmp($this->comparison, Comparison::GREATER) === 0) {

            return $filter > $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, Comparison::GREATER_OR_EQUAL) === 0) {

            return $filter >= $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, Comparison::IN) === 0) {

            if (strpos($this->compareValue, $filter) === false) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, Comparison::NOT) === 0) {

            if (strpos($this->compareValue, $filter) !== false) {
                return false;
            }

            return true;
        }

        return true;
    }
}