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
        $this->compareValue = explode(",", trim(str_replace(";", ",", $compareValue)));
    }

    public function filter($filter)
    {
        if (!is_numeric($filter)) {
            return 2;
        }

        if (strcmp($this->comparison, FilterType::IN) === 0) {

            if (!in_array($filter, $this->compareValue)) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::NOT_IN) === 0) {

            if (in_array($filter, $this->compareValue)) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::SMALLER) === 0) {

            return $filter < $this->compareValue[0] ? true : false;
        }

        if (strcmp($this->comparison, FilterType::SMALLER_OR_EQUAL) === 0) {

            return $filter <= $this->compareValue[0] ? true : false;
        }

        if (strcmp($this->comparison, FilterType::EQUAL) === 0) {

            return $this->compareValue[0] === $filter ? true : false;
        }

        if (strcmp($this->comparison, FilterType::NOT_EQUAL) === 0) {

            return $this->compareValue[0] !== $filter ? true : false;
        }

        if (strcmp($this->comparison, FilterType::GREATER) === 0) {

            return $filter > $this->compareValue[0] ? true : false;
        }

        if (strcmp($this->comparison, FilterType::GREATER_OR_EQUAL) === 0) {

            return $filter >= $this->compareValue[0] ? true : false;
        }

        return true;
    }
}