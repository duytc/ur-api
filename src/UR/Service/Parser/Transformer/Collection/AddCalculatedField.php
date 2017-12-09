<?php

namespace UR\Service\Parser\Transformer\Collection;

use Exception;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\Parser\ReformatDataService;

class AddCalculatedField extends AbstractAddField implements CollectionTransformerJsonConfigInterface
{
    const EXPRESSION_KEY = 'expression';
    const DEFAULT_VALUES_KEY = 'defaultValues';
    const CONDITION_FIELD_KEY = 'conditionField';
    const CONDITION_COMPARATOR_KEY = 'conditionComparator';
    const CONDITION_VALUE_KEY = 'conditionValue';
    const DEFAULT_VALUE_KEY = 'defaultValue';


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

    public static $INVALID_VALUES = [NAN, INF, NULL];

    /** @var string */
    protected $column;
    /** @var string */
    protected $expression;
    /** @var null|array */
    protected $defaultValues;
    /** @var ExpressionLanguage */
    protected $expressionLanguage;

    private $reformatDataService;

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
        $this->reformatDataService = new ReformatDataService();

        $this->expressionLanguage->register('abs', function ($number) {
            return sprintf('(is_numeric(%1$s) ? abs(%1$s) : %1$s)', $number);
        }, function ($arguments, $number) {
            if (!is_numeric($number)) {
                return $number;
            }

            return abs($number);
        });
    }

    /**
     * @inheritdoc
     */
    protected function getValue(array $row)
    {
        try {
            $expressionForm = $this->convertExpressionForm($this->expression, $row);
            if ($expressionForm != NULL) {
                $row[$this->column] = $this->expressionLanguage->evaluate($expressionForm, ['row' => $row]);
            } else {
                $row[$this->column] = null;
            }
        } catch (\Exception $exception) {
            $row[$this->column] = null;
        }

        return $this->getDefaultValueByInputFieldCondition($row, $isMatched);
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

            if (!empty($row[$field]) && !is_numeric($row[$field])) {
                return null;
            }

            // always convert empty value to zero
            $replaceString = empty($row[$field]) ? '0' : sprintf('row[\'%s\']', $field);
            $expression = str_replace($fieldsInBracket[$index], $replaceString, $expression);
        }

        return $expression;
    }

    /**
     * getDefaultValueByValue
     *
     * @param array $row
     * @param bool $isMatched
     * @return null
     */
    private function getDefaultValueByInputFieldCondition(array $row, &$isMatched)
    {
        if (!is_array($this->defaultValues)) {
            $isMatched = false;
            return NULL;
        }

        foreach ($this->defaultValues as $defaultValueConfig) {
            $conditionField = $defaultValueConfig[self::CONDITION_FIELD_KEY];
            $conditionComparator = $defaultValueConfig[self::CONDITION_COMPARATOR_KEY];
            $conditionValue = $defaultValueConfig[self::CONDITION_VALUE_KEY];
            $defaultValue = $defaultValueConfig[self::DEFAULT_VALUE_KEY];

            if ($conditionField == self::CONDITION_FIELD_CALCULATED_VALUE) {
                $conditionField = $this->column;
            }
            // TODO: should remove check in_array($conditionValue, self::$INVALID_VALUES) ????
            // this related to case compare calculatedValue with simple value, not is invalid
            // or related to case compare field from file or data set is invalid
            if (!array_key_exists($conditionField, $row)) {
                continue;
            }

            // TODO: add filter isInvalid for field from file or data set ????

            $valueForCompare = $row[$conditionField];

            // FIRST MATCH, FIRST SERVE. PLEASE MIND THE ORDER

            $isMatched = $this->matchCondition($valueForCompare, $conditionComparator, $conditionValue);
            if ($isMatched) {
                return $defaultValue;
            }
        }

        if (array_key_exists($this->column, $row)) {
            return $row[$this->column];
        }

        return NULL;
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
            case self::CONDITION_COMPARISON_VALUE_IN:
                return in_array(strval($value), $conditionValue);

            case self::CONDITION_COMPARISON_VALUE_NOT_IN:
                return !in_array($value, $conditionValue);

            case self::CONDITION_COMPARISON_VALUE_SMALLER:
                return $value < $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_SMALLER_OR_EQUAL:
                return $value <= $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_EQUAL:
                return strval($value) == $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_NOT_EQUAL:
                return strval($value) != $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER:
                return $value > $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER_OR_EQUAL:
                return $value >= $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_IS_INVALID:
                return is_null($value) || ($value != 0 && in_array($value, self::$INVALID_VALUES));
        }

        // default not match
        return false;
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

    /**
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @return array
     */
    public function getDefaultValues()
    {
        return $this->defaultValues;
    }

    /**
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * @param string $column
     */
    public function setColumn(string $column)
    {
        $this->column = $column;
    }

    /**
     * @param string $expression
     */
    public function setExpression(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * @param array|null $defaultValues
     */
    public function setDefaultValues($defaultValues)
    {
        $this->defaultValues = $defaultValues;
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->column;
        $transformFields[self::DEFAULT_VALUES_KEY] = $this->defaultValues;
        $transformFields[self::EXPRESSION_KEY] = $this->expression;
        return $transformFields;
    }
}