<?php

namespace UR\Domain\DTO\Report\Transforms;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform extends NewFieldTransform implements TransformInterface
{
    const PRIORITY = 3;
    const TRANSFORMS_TYPE = 'addCalculatedField';

    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValues';
    const DEFAULT_VALUE_KEY = 'defaultValue';
    const CONDITION_FIELD_KEY = 'conditionField';
    const CONDITION_COMPARATOR_KEY = 'conditionComparator';
    const CONDITION_VALUE_KEY = 'conditionValue';
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
    const INVALID_VALUE = NULL;

    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_NAME_KEY, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::TYPE_KEY, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either "field" or "expression" or "type" does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::FIELD_NAME_KEY];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
        $this->type = $addCalculatedField[self::TYPE_KEY];
        if (isset($addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD]) && is_array($addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD])) {
            $this->defaultValue = $addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD];
        } else {
            $this->defaultValue = [];
        }
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $outputJoinField
     * @return mixed|void
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        parent::transform($collection, $metrics, $dimensions, $outputJoinField);
        $expressionForm = $this->convertExpressionForm($this->expression);
        if ($expressionForm === null) {
            return;
        }

        $rows = $collection->getRows();
        foreach ($rows as &$row) {
            try {
                $calculatedValue = $this->language->evaluate($expressionForm, ['row' => $row]);
            } catch (\Exception $ex) {
                $calculatedValue = self::INVALID_VALUE;
            }

            $row[$this->fieldName] = $this->getDefaultValueByCondition($calculatedValue, $row);;
        }

        $collection->setRows($rows);
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
        if (!is_array($this->defaultValue)) {
            return $value;
        }

        foreach ($this->defaultValue as $defaultValueConfig) {
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
                return $value == $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_NOT_EQUAL:
                return $value != $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER:
                return $value > $conditionValue;

            case self::CONDITION_COMPARISON_VALUE_GREATER_OR_EQUAL:
                return $value >= $conditionValue;
        }

        // default not match
        return false;
    }

    /**
     * Convert expression from: [fie1d_id]-[field2_id]  to row['field_id'] - row['field2_id']
     * @param $expression
     * @throws \Exception
     * @return mixed
     */
    protected function convertExpressionForm($expression)
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
            $replaceString = sprintf('row[\'%s\']', $field);
            $expression = str_replace($fieldsInBracket[$index], $replaceString, $expression);
        }

        return $expression;
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }
}