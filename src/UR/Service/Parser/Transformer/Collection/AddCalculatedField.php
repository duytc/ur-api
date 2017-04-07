<?php

namespace UR\Service\Parser\Transformer\Collection;

use Exception;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class AddCalculatedField extends AbstractAddField
{
    const EXPRESSION_KEY = 'expression';
    const DEFAULT_VALUES_KEY = 'defaultValues';
    const CONDITION_FIELD_KEY = 'conditionField';
    const CONDITION_COMPARATOR_KEY = 'conditionComparator';
    const CONDITION_VALUE_KEY = 'conditionValue';
    const DEFAULT_VALUE_KEY = 'defaultValue';
    const INVALID_VALUE = NULL;

    const CONDITION_FIELD_CALCULATED_VALUE = '$$CALCULATED_VALUE$$';

    const CONDITION_COMPARISON_VALUE_EQUAL = 'equal';
    const CONDITION_COMPARISON_VALUE_SMALLER = 'smaller';
    const CONDITION_COMPARISON_VALUE_SMALLER_OR_EQUAL = 'smaller or equal';
    const CONDITION_COMPARISON_VALUE_GREATER = 'greater';
    const CONDITION_COMPARISON_VALUE_GREATER_OR_EQUAL = 'greater or equal';
    const CONDITION_COMPARISON_VALUE_NOT_EQUAL = 'not equal';
    const CONDITION_COMPARISON_VALUE_IN = 'in';
    const CONDITION_COMPARISON_VALUE_NOT_IN = 'not in';
    const CONDITION_COMPARISON_VALUE_IS_INVALID = 'is invalid';

    const EPSILON = 10e-12;

    public static $SUPPORTED_CONDITION_COMPARISON_VALUES = [
        self::CONDITION_COMPARISON_VALUE_EQUAL,
        self::CONDITION_COMPARISON_VALUE_SMALLER,
        self::CONDITION_COMPARISON_VALUE_SMALLER_OR_EQUAL,
        self::CONDITION_COMPARISON_VALUE_GREATER,
        self::CONDITION_COMPARISON_VALUE_GREATER_OR_EQUAL,
        self::CONDITION_COMPARISON_VALUE_NOT_EQUAL,
        self::CONDITION_COMPARISON_VALUE_IN,
        self::CONDITION_COMPARISON_VALUE_NOT_IN,
        self::CONDITION_COMPARISON_VALUE_IS_INVALID
    ];

    /** @var string */
    protected $column;
    /** @var string */
    protected $expression;
    /** @var null|array */
    protected $defaultValues;
    /** @var ExpressionLanguage */
    protected $expressionLanguage;

    /**
     * AddCalculatedField constructor.
     * @param string $column
     * @param $expression
     * @param null|array $defaultValues
     */
    public function __construct($column, $expression, $defaultValues = null)
    {
        parent::__construct($column);

        $this->expression = $expression;
        
        if (is_array($defaultValues)) {
            $this->defaultValues = $defaultValues;
        } else $this->defaultValues = [];

        $this->expressionLanguage = new ExpressionLanguage();
    }

    /**
     * @inheritdoc
     */
    protected function getValue(array $row)
    {
        // execute expression to get value
        try {
            $this->expressionLanguage->register('abs', function ($number) {
                return sprintf('(is_numeric(%1$s) ? abs(%1$s) : %1$s)', $number);
            }, function ($arguments, $number) {
                if (!is_numeric($number)) {
                    return $number;
                }

                return abs($number);
            });

            $expressionForm = $this->convertExpressionForm($this->expression, $row);
            if ($expressionForm === null) {
                return self::INVALID_VALUE;
            }

            $result = $this->expressionLanguage->evaluate($expressionForm, ['row' => $row]);
        } catch (\Exception $exception) {
            $result = self::INVALID_VALUE;
        }

        // get value by condition ($this->defaultValues config)
        return $this->getDefaultValueByCondition($result, $row);
    }

    /**
     * @param $expression
     * @param array $row
     * @return mixed|null
     * @throws \Exception
     */
    protected function convertExpressionForm($expression, array $row)
    {
        if (is_null($expression)) {
            throw new \Exception(sprintf('Expression for calculated field can not be null'));
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fieldsInBracket = $matches[0];
        $fields = $matches[1];
        $newExpressionForm = null;

        foreach ($fields as $index => $field) {
            if (!array_key_exists($field, $row)) {
                return null;
            }

            if (!is_numeric($row[$field])) {
                return null;
            }

            $replaceString = sprintf('row[\'%s\']', $field);
            $expression = str_replace($fieldsInBracket[$index], $replaceString, $expression);
        }

        return $expression;
    }

    /**
     * getDefaultValueByValue
     *
     * @param $value
     * @param array $row
     * @return null
     */
    private function getDefaultValueByCondition($value, array $row)
    {
        if (!is_array($this->defaultValues)) {
            return $value;
        }

        foreach ($this->defaultValues as $defaultValueConfig) {
            // not need validate again for defaultValueConfig due to already validated before
            $conditionField = $defaultValueConfig[self::CONDITION_FIELD_KEY];
            $conditionComparator = $defaultValueConfig[self::CONDITION_COMPARATOR_KEY];
            $conditionValue = $defaultValueConfig[self::CONDITION_VALUE_KEY];
            $defaultValue = $defaultValueConfig[self::DEFAULT_VALUE_KEY];

            // find value for compare
            // value may be value of field or current calculated field
            $valueForCompare = $value; // default value of CALCULATED_FIELD

            if (self::CONDITION_FIELD_CALCULATED_VALUE !== $conditionField) {
                // only get value from field in row if existed
                if (!array_key_exists($conditionField, $row)) {
                    continue; // field not found in row, abort check this condition
                }

                $valueForCompare = $row[$conditionField];
            }

            // do match condition and return default value if matched
            // FIRST MATCH, FIRST SERVE. PLEASE MIND THE ORDER
            $isMatched = $this->matchCondition($valueForCompare, $conditionComparator, $conditionValue);
            if ($isMatched) {
                return $defaultValue;
            }
        }

        // not condition matched, return the original value
        return $value;
    }

    /**
     * do Compare
     *
     * @param mixed $value
     * @param mixed $conditionComparator
     * @param mixed $conditionValue
     * @return bool false if not matched
     */
    private function matchCondition($value, $conditionComparator, $conditionValue)
    {
        switch ($conditionComparator) {
            case self::CONDITION_COMPARISON_VALUE_IS_INVALID:
                return $value === self::INVALID_VALUE;

            case self::CONDITION_COMPARISON_VALUE_IN:
                return in_array($value, $conditionValue);

            case self::CONDITION_COMPARISON_VALUE_NOT_IN:
                return !in_array($value, $conditionValue);

            case self::CONDITION_COMPARISON_VALUE_SMALLER:
                return $value < $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_SMALLER_OR_EQUAL:
                return $value <= $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_EQUAL:
                return $value === $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_NOT_EQUAL:
                return $value !== $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER:
                return $value > $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER_OR_EQUAL:
                return $value >= $conditionValue;
        }

        // default not match
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_ADD_CALCULATED_FIELD;
    }

    /**
     * @inheritdoc
     */
    public function validate()
    {
        /*
         * sample json
         * {
         *    defaultValues: [
         *      {
         *        conditionField: impression,
         *        conditionComparator: lessThan,
         *        conditionValue: 1,
         *        defaultValue: 0
         *       },
         *       ...
         *    ]
         * }
         */
        if (!is_array($this->defaultValues)) {
            return true;
        }

        foreach ($this->defaultValues as $defaultValueConfig) {
            // validate key
            if (!array_key_exists(self::CONDITION_FIELD_KEY, $defaultValueConfig)
                || !array_key_exists(self::CONDITION_COMPARATOR_KEY, $defaultValueConfig)
                || !array_key_exists(self::CONDITION_VALUE_KEY, $defaultValueConfig)
                || !array_key_exists(self::DEFAULT_VALUE_KEY, $defaultValueConfig)
            ) {
                throw new Exception(sprintf('Missing either key "%s" or "%s" or "%s" or "%s" in defaultValues config',
                    self::CONDITION_FIELD_KEY,
                    self::CONDITION_COMPARATOR_KEY,
                    self::CONDITION_VALUE_KEY,
                    self::DEFAULT_VALUE_KEY
                ));
            }

            // validate condition comparator
            $conditionComparator = $defaultValueConfig[self::CONDITION_COMPARATOR_KEY];
            if (!in_array($conditionComparator, self::$SUPPORTED_CONDITION_COMPARISON_VALUES)) {
                throw new Exception(sprintf('Not support conditionComparator "%s" in defaultValues config', $conditionComparator));
            }
        }

        return true;
    }
}