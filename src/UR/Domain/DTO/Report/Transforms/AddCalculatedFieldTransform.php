<?php

namespace UR\Domain\DTO\Report\Transforms;

use SplDoublyLinkedList;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform extends NewFieldTransform implements TransformInterface
{
    const TRANSFORMS_TYPE = 'addCalculatedField';
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValues';
    const DEFAULT_VALUE_KEY = 'defaultValue';
    const CONDITION_FIELD_KEY = 'conditionField';
    const CONDITION_COMPARATOR_KEY = 'conditionComparator';
    const CONDITION_VALUE_KEY = 'conditionValue';
    const CONDITION_FIELD_CALCULATED_VALUE = '$$CALCULATED_VALUE$$';

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
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $index => $row) {
            try {
                $calculatedValue = $this->language->evaluate($expressionForm, ['row' => $row]);
            } catch (\Exception $ex) {
                $calculatedValue = self::INVALID_VALUE;
            }

            $value = $this->getDefaultValueByCondition($calculatedValue, $row);

            // json does not support NAN or INF values
            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                $value = null;
            }
            $row[$this->fieldName] = $value;
            $newRows->push($row);
        }

        unset($rows, $row);
        $collection->setRows($newRows);
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

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }

    /**
     * @return array
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }
}