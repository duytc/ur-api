<?php

namespace UR\Service\Parser\Filter;


class TextFilter extends AbstractFilter implements ColumnFilterInterface
{
    const COMPARISON_TYPE_EQUAL = 'equal';
    const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
    const COMPARISON_TYPE_CONTAINS = 'contains';
    const COMPARISON_TYPE_NOT_CONTAINS = 'not contains';
    const COMPARISON_TYPE_START_WITH = 'start with';
    const COMPARISON_TYPE_END_WITH = 'end with';
    const COMPARISON_TYPE_IN = 'in';
    const COMPARISON_TYPE_NOT_IN = 'not in';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    public static $SUPPORTED_COMPARISON_TYPES = [
        self::COMPARISON_TYPE_EQUAL,
        self::COMPARISON_TYPE_NOT_EQUAL,
        self::COMPARISON_TYPE_CONTAINS,
        self::COMPARISON_TYPE_NOT_CONTAINS,
        self::COMPARISON_TYPE_START_WITH,
        self::COMPARISON_TYPE_END_WITH,
        self::COMPARISON_TYPE_IN,
        self::COMPARISON_TYPE_NOT_IN
    ];

    /** @var string */
    protected $comparisonType;

    /** @var string|array due to comparisonType */
    protected $comparisonValue;

    /**
     * TextFilter constructor.
     * @param $field
     * @param $comparisonType
     * @param array|string $comparisonValue
     */
    public function __construct($field, $comparisonType, $comparisonValue)
    {
        parent::__construct($field);
        $this->comparisonType = $comparisonType;
        $this->comparisonValue = $comparisonValue;
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

    public function validate()
    {
        $this->validateComparisonType();
        $this->validateComparisonValue();
    }


    /**
     * validate ComparisonType
     *
     * @throws \Exception
     */
    private function validateComparisonType()
    {
        if (!in_array($this->comparisonType, self::$SUPPORTED_COMPARISON_TYPES)) {
            throw new \Exception(sprintf('Not supported comparisonType "%s"', $this->comparisonType));
        }
    }

    /**
     * validate ComparisonValue
     *
     * @throws \Exception
     */
    private function validateComparisonValue()
    {
        // expect array
        if (!is_array($this->comparisonValue)) {
            throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType "%s", got %s', $this->comparisonType, $this->comparisonValue));
        }
    }
}