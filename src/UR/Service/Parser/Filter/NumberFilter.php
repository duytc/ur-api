<?php

namespace UR\Service\Parser\Filter;


use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;

class NumberFilter extends AbstractFilter implements ColumnFilterInterface
{
    const COMPARISON_TYPE_EQUAL = 'equal';
    const COMPARISON_TYPE_SMALLER = 'smaller';
    const COMPARISON_TYPE_SMALLER_OR_EQUAL = 'smaller or equal';
    const COMPARISON_TYPE_GREATER = 'greater';
    const COMPARISON_TYPE_GREATER_OR_EQUAL = 'greater or equal';
    const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
    const COMPARISON_TYPE_IN = 'in';
    const COMPARISON_TYPE_NOT_IN = 'not in';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';
    const COMPARISON_TYPE_NULL = 'isEmpty';
    const COMPARISON_TYPE_NOT_NULL = 'isNotEmpty';

    const EPSILON = 10e-12;

    public static $SUPPORTED_COMPARISON_TYPES = [
        self::COMPARISON_TYPE_EQUAL,
        self::COMPARISON_TYPE_SMALLER,
        self::COMPARISON_TYPE_SMALLER_OR_EQUAL,
        self::COMPARISON_TYPE_GREATER,
        self::COMPARISON_TYPE_GREATER_OR_EQUAL,
        self::COMPARISON_TYPE_NOT_EQUAL,
        self::COMPARISON_TYPE_IN,
        self::COMPARISON_TYPE_NOT_IN,
        self::COMPARISON_TYPE_NULL,
        self::COMPARISON_TYPE_NOT_NULL
    ];

    /** @var string */
    protected $comparisonType;

    /** @var string|array due to comparisonType */
    protected $comparisonValue;

    /**
     * NumberFilter constructor.
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
        if (self::COMPARISON_TYPE_NOT_NULL === $this->comparisonType) {
            return $value != null;
        }

        if (self::COMPARISON_TYPE_NULL === $this->comparisonType) {
            return $value == null;
        }

        if ($value === null || $value === "") {
            return false;
        }

        if (!is_numeric($value)) {
            return ImportFailureAlert::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER;
        }

        if (self::COMPARISON_TYPE_IN === $this->comparisonType) {
            if (!in_array($value, $this->comparisonValue)) {
                return false;
            }

            return true;
        }

        if (self::COMPARISON_TYPE_NOT_IN === $this->comparisonType) {
            if (in_array($value, $this->comparisonValue)) {
                return false;
            }

            return true;
        }

        if (self::COMPARISON_TYPE_SMALLER === $this->comparisonType) {
            return $value < $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_SMALLER_OR_EQUAL === $this->comparisonType) {
            return $value <= $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_EQUAL === $this->comparisonType) {
            return abs(floatval($this->comparisonValue) - floatval($value)) < self::EPSILON ? true : false;
        }

        if (self::COMPARISON_TYPE_NOT_EQUAL === $this->comparisonType) {
            return abs(floatval($this->comparisonValue) - floatval($value)) >= self::EPSILON ? true : false;
        }

        if (self::COMPARISON_TYPE_GREATER === $this->comparisonType) {
            return $value > $this->comparisonValue ? true : false;
        }

        if (self::COMPARISON_TYPE_GREATER_OR_EQUAL === $this->comparisonType) {
            return $value >= $this->comparisonValue ? true : false;
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
            throw new \Exception(sprintf('Not supported comparisonType %s', $this->comparisonType));
        }
    }

    /**
     * validate ComparisonValue
     *
     * @throws \Exception
     */
    private function validateComparisonValue()
    {
        if ($this->comparisonType == self::COMPARISON_TYPE_NULL || $this->comparisonType == self::COMPARISON_TYPE_NOT_NULL) {
            return;
        }

        // expect array
        if ($this->comparisonType == self::COMPARISON_TYPE_IN
            || $this->comparisonType == self::COMPARISON_TYPE_NOT_IN
        ) {
            if (!is_array($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType %s', $this->comparisonType));
            }

            foreach ($this->comparisonValue as $cv) {
                if (!is_numeric($cv)) {
                    throw new \Exception(sprintf('Expect comparisonValue is array of numeric with comparisonType %s', $this->comparisonType));
                }
            }
        } else {
            // expect single value
            if (!is_numeric($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is numeric with comparisonType %s', $this->comparisonType));
            }
        }
    }
}