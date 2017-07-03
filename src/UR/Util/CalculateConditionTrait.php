<?php


namespace UR\Util;


trait CalculateConditionTrait
{
    protected $conditionComparisonValueEqual = 'equal';
    protected $conditionComparisonValueSmaller = 'smaller';
    protected $conditionComparisonValueSmallerOrEqual = 'smaller or equal';
    protected $conditionComparisonValueGreater = 'greater';
    protected $conditionComparisonValueGreaterOrEqual = 'greater or equal';
    protected $conditionComparisonValueNotEqual = 'not equal';
    protected $conditionComparisonValueIn = 'in';
    protected $conditionComparisonValueNotIn = 'not in';
    protected $conditionComparisonValueIsInValid = 'is invalid';
    protected $invalidValue = NULL;
    protected $conditionComparisonValueContain = 'contain';
    protected $conditionComparisonValueNotContain = 'not contain';

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
            case $this->conditionComparisonValueIsInValid:
                return $value == $this->invalidValue;
            case $this->conditionComparisonValueIn:
                return in_array($value, $conditionValue);
            case $this->conditionComparisonValueNotIn:
                return !in_array($value, $conditionValue);
            case $this->conditionComparisonValueSmaller:
                return $value < $conditionValue;
            case $this->conditionComparisonValueSmallerOrEqual:
                return $value <= $conditionValue;
            case $this->conditionComparisonValueEqual:
                return $value == $conditionValue;
            case $this->conditionComparisonValueNotEqual:
                return $value != $conditionValue;
            case $this->conditionComparisonValueGreater:
                return $value > $conditionValue;
            case $this->conditionComparisonValueGreaterOrEqual:
                return $value >= $conditionValue;
            case $this->conditionComparisonValueContain:
                return false == strpos($value, $conditionValue);
            case $this->conditionComparisonValueNotContain:
                return false != strpos($value, $conditionValue);
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
}