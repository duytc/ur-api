<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;
use UR\Util\CalculateConditionTrait;

class AddFieldTransform extends NewFieldTransform implements TransformInterface
{
    use CalculateConditionTrait;

    const TRANSFORMS_TYPE = 'addField';
    const FIELD_VALUE = 'value';

    const FIELD_CONDITIONS = 'conditions';
    const FIELD_CONDITIONS_EXPRESSIONS = 'expressions';
    const FIELD_CONDITIONS_VALUE = 'value';

    const FIELD_CONDITIONS_EXPRESSIONS_VAR = 'var';
    const FIELD_CONDITIONS_EXPRESSIONS_CMP = 'cmp';
    const FIELD_CONDITIONS_EXPRESSIONS_VAL = 'val';

    protected $value;
    protected $conditions;

    /**
     * AddFieldTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_NAME_KEY, $data) || !array_key_exists(self::FIELD_VALUE, $data) || !array_key_exists(self::TYPE_KEY, $data)) {
            throw new InvalidArgumentException('either "fields" or "fieldValue" or "type" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->value = $data[self::FIELD_VALUE];
        $this->type = $data[self::TYPE_KEY];

        if (array_key_exists(self::FIELD_CONDITIONS, $data)) {
            $this->conditions = $data[self::FIELD_CONDITIONS];
        } else {
            $this->conditions = [];
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

        $rows = $collection->getRows();
//        if (is_numeric($this->fieldName)) {
//            $this->fieldName = strval($this->fieldName);
//        }

        $newRows = array_map(function ($row) {
            $conditionValue = $this->getValueByCondition($row);
            $row[$this->fieldName] = $conditionValue;
            return $row;
        }, $rows);

        $collection->setRows($newRows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }

    /**
     * @param $row
     * @return mixed
     */
    private function getValueByCondition($row)
    {
        foreach ($this->conditions as $condition) {
            if (!array_key_exists(self::FIELD_CONDITIONS_EXPRESSIONS, $condition) ||
                !array_key_exists(self::FIELD_CONDITIONS_VALUE, $condition)
            ) {
                continue;
            }

            $expressions = $condition[self::FIELD_CONDITIONS_EXPRESSIONS];
            $conditionValue = $condition[self::FIELD_CONDITIONS_VALUE];

            if (count($expressions) < 1) {
                continue;
            }

            $isMatch = true;
            foreach ($expressions as $expression) {
                if (!$this->validRowWithExpression($row, $expression)) {
                    $isMatch = false;
                    break;
                }
            }

            /** Return condition value if match all expression of this condition */
            if ($isMatch) {
                return $conditionValue;
            }
        }

        /** If not match any condition, return default value of add field transform */
        return $this->value;
    }

    /**
     * @param $row
     * @param $expression
     * @return bool
     */
    private function validRowWithExpression($row, $expression)
    {
        if (!array_key_exists(self::FIELD_CONDITIONS_EXPRESSIONS_CMP, $expression) ||
            !array_key_exists(self::FIELD_CONDITIONS_EXPRESSIONS_VAL, $expression) ||
            !array_key_exists(self::FIELD_CONDITIONS_EXPRESSIONS_VAR, $expression)
        ) {
            return false;
        }

        $conditionComparator = $expression[self::FIELD_CONDITIONS_EXPRESSIONS_CMP];
        $conditionValue = $expression[self::FIELD_CONDITIONS_EXPRESSIONS_VAL];
        $field = $expression[self::FIELD_CONDITIONS_EXPRESSIONS_VAR];

        $field = preg_replace('/[\[\]]/', '' , $field);

        /** Check if row have field compare or not */
        if (!array_key_exists($field, $row)) {
            return false;
        }

        return $this->matchCondition($row[$field], $conditionComparator, $conditionValue);
    }
}