<?php

namespace UR\Service\Parser\Filter;


use UR\Service\Alert\ProcessAlert;

class NumberFilter extends \UR\Domain\DTO\Report\Filters\NumberFilter implements ColumnFilterInterface
{
    public function __construct(array $numberFilter)
    {
        parent::__construct($numberFilter);
    }

    /**
     * @inheritdoc
     */
    public function filter($filter)
    {
        if ($filter === null || $filter === "") {
            return false;
        }

        if (!is_numeric($filter)) {
            return ProcessAlert::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER;
        }

        if (self::COMPARISON_TYPE_IN === $this->comparisonType) {
            if (!is_array($this->comparisonValue)) {
                return false;
            }

            if (!in_array($filter, $this->comparisonValue)) {
                return false;
            }

            return true;
        }

        if (self::COMPARISON_TYPE_NOT_IN === $this->comparisonType) {
            if (!is_array($this->comparisonValue)) {
                return false;
            }

            if (in_array($filter, $this->comparisonValue)) {
                return false;
            }

            return true;
        }

        if (self::COMPARISON_TYPE_SMALLER === $this->comparisonType) {
            return $filter < $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_SMALLER_OR_EQUAL === $this->comparisonType) {
            return $filter <= $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_EQUAL === $this->comparisonType) {
            return abs(floatval($this->comparisonValue) - floatval($filter)) < self::EPSILON ? true : false;
        }

        if (self::COMPARISON_TYPE_NOT_EQUAL === $this->comparisonType) {
            return $this->comparisonValue !== $filter ? true : false;
        }

        if (self::COMPARISON_TYPE_GREATER === $this->comparisonType) {
            return $filter > $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_GREATER_OR_EQUAL === $this->comparisonType) {
            return $filter >= $this->comparisonValue ? true : false;
        }

        return true;
    }
}