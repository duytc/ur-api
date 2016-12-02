<?php

namespace UR\Service\Parser\Filter;

use UR\Exception\InvalidArgumentException;
use UR\Service\DataSet\FilterType;

class TextFilter implements ColumnFilterInterface
{
    /** @var string */
    protected $comparison;
    /** @var string|array due to comparison */
    protected $compareValue;

    public function __construct($comparison, $compareValue)
    {
        $this->comparison = $comparison;

        if (!is_array($compareValue)) {
            throw new InvalidArgumentException('Expect compareValue is array, got ' . $compareValue);
        }

        $this->compareValue = array_map(function ($cv) {
            return strtolower($cv);
        }, $compareValue);
    }

    /**
     * @inheritdoc
     */
    public function filter($value)
    {
        $value = strtolower($value);

        if (!is_array($this->compareValue)) {
            return false;
        }

        if (FilterType::CONTAINS === $this->comparison) {
            foreach ($this->compareValue as $compareValue) {
                if (strpos($value, $compareValue) !== false) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (FilterType::NOT_CONTAINS === $this->comparison) {
            foreach ($this->compareValue as $compareValue) {
                if (strpos($value, $compareValue) !== false) {
                    return false; // decline if one not satisfied
                }
            }

            return true;
        }

        if (FilterType::START_WITH === $this->comparison) {
            foreach ($this->compareValue as $compareValue) {
                if (substr($value, 0, strlen($compareValue)) === $compareValue) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (FilterType::END_WITH === $this->comparison) {
            foreach ($this->compareValue as $compareValue) {
                if (substr($value, 0 - strlen($compareValue)) === $compareValue) {
                    return true; // accept if one satisfied
                }
            }

            return false;
        }

        if (FilterType::IN === $this->comparison) {
            return in_array($value, $this->compareValue);
        }

        if (FilterType::NOT_IN === $this->comparison) {
            return !in_array($value, $this->compareValue);
        }

        return true;
    }
}