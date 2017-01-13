<?php

namespace UR\Service\Parser\Filter;


class TextFilter extends CommonTextFilter implements ColumnFilterInterface
{
    public function __construct(array $textFilter)
    {
        parent::__construct($textFilter);
    }

    /**
     * @inheritdoc
     */
    public function filter($value)
    {
        $value = strtolower($value);
        $this->comparisonValue = array_map('strtolower', $this->comparisonValue);

        if (!is_array($this->comparisonValue)) {
            return false;
        }

        if (self::COMPARISON_TYPE_CONTAINS === $this->comparisonType) {
            foreach ($this->comparisonValue as $comparisonValue) {
                if (strpos($value, $comparisonValue) !== false) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (self::COMPARISON_TYPE_NOT_CONTAINS === $this->comparisonType) {
            foreach ($this->comparisonValue as $comparisonValue) {
                if (strpos($value, $comparisonValue) !== false) {
                    return false; // decline if one not satisfied
                }
            }

            return true;
        }

        if (self::COMPARISON_TYPE_START_WITH === $this->comparisonType) {
            foreach ($this->comparisonValue as $comparisonValue) {
                if (substr($value, 0, strlen($comparisonValue)) === $comparisonValue) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (self::COMPARISON_TYPE_END_WITH === $this->comparisonType) {
            foreach ($this->comparisonValue as $comparisonValue) {
                if (substr($value, 0 - strlen($comparisonValue)) === $comparisonValue) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (self::COMPARISON_TYPE_IN === $this->comparisonType) {
            return in_array($value, $this->comparisonValue);
        }

        if (self::COMPARISON_TYPE_NOT_IN === $this->comparisonType) {
            return !in_array($value, $this->comparisonValue);
        }

        return true;
    }
}