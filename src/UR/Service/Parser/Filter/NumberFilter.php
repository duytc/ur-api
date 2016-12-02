<?php

namespace UR\Service\Parser\Filter;

use UR\Exception\InvalidArgumentException;
use UR\Service\DataSet\FilterType;

class NumberFilter implements ColumnFilterInterface
{
    protected $comparison;
    protected $compareValue;

    public function __construct($comparison, $compareValue)
    {
        $this->comparison = $comparison;
        $this->compareValue = $compareValue;

        // validate special cases
        if (FilterType::IN === $this->comparison || FilterType::NOT_IN === $this->comparison) {
            if (!is_array($this->compareValue)) {
                throw new InvalidArgumentException('expect compareValue is array for cases IN and NOT_IN');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function filter($filter)
    {
        if (!is_numeric($filter)) {
            return 2;
        }

        if (FilterType::IN === $this->comparison) {
            if (!is_array($this->compareValue)) {
                return false;
            }

            if (!in_array($filter, $this->compareValue)) {
                return false;
            }

            return true;
        }

        if (FilterType::NOT_IN === $this->comparison) {
            if (!is_array($this->compareValue)) {
                return false;
            }

            if (in_array($filter, $this->compareValue)) {
                return false;
            }

            return true;
        }

        if (FilterType::SMALLER === $this->comparison) {

            return $filter < $this->compareValue[0] ? true : false;
        }

        if (FilterType::SMALLER_OR_EQUAL === $this->comparison) {

            return $filter <= $this->compareValue[0] ? true : false;
        }

        if (FilterType::EQUAL === $this->comparison) {

            return $this->compareValue[0] === $filter ? true : false;
        }

        if (FilterType::NOT_EQUAL === $this->comparison) {

            return $this->compareValue[0] !== $filter ? true : false;
        }

        if (FilterType::GREATER === $this->comparison) {

            return $filter > $this->compareValue[0] ? true : false;
        }

        if (FilterType::GREATER_OR_EQUAL === $this->comparison) {

            return $filter >= $this->compareValue[0] ? true : false;
        }

        return true;
    }
}