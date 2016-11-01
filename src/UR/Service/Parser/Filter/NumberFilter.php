<?php

namespace UR\Service\Parser\Filter;

use UR\Service\DataSet\FilterType;

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
        if (strcmp($this->comparison, FilterType::SMALLER) === 0) {

            return $filter < $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, FilterType::SMALLER_OR_EQUAL) === 0) {

            return $filter <= $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, FilterType::EQUAL) === 0) {

            return $this->compareValue === $filter ? true : false;
        }

        if (strcmp($this->comparison, FilterType::NOT_EQUAL) === 0) {

            return $this->compareValue !== $filter ? true : false;
        }

        if (strcmp($this->comparison, FilterType::GREATER) === 0) {

            return $filter > $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, FilterType::GREATER_OR_EQUAL) === 0) {

            return $filter >= $this->compareValue ? true : false;
        }

        if (strcmp($this->comparison, FilterType::IN) === 0) {

            if (strpos($this->compareValue, $filter) === false) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::NOT) === 0) {

            if (strpos($this->compareValue, $filter) !== false) {
                return false;
            }

            return true;
        }

        return true;
    }
}