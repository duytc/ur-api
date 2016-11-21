<?php

namespace UR\Service\Parser\Filter;

use UR\Service\DataSet\FilterType;

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
        if (strcmp($this->comparison, FilterType::CONTAINS) === 0) {

            if (strpos($filter, $this->compareValue) === false) {
                return false;
            }
            return true;
        }

        if (strcmp($this->comparison, FilterType::NOT_CONTAINS) === 0) {

            if (strpos($filter, $this->compareValue) !== false) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::START_WITH) === 0) {

            if (substr($filter, 0, strlen($this->compareValue)) !== $this->compareValue) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::END_WITH) === 0) {

            if (substr($filter, 0 - strlen($this->compareValue)) !== $this->compareValue) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::IN) === 0) {

            if (strpos($this->compareValue, $filter) === false) {
                return false;
            }

            return true;
        }

        if (strcmp($this->comparison, FilterType::NOT_IN) === 0) {

            if (strpos($this->compareValue, $filter) !== false) {
                return false;
            }

            return true;
        }

        return true;
    }
}