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
    protected $conditionComparisonValueBetween = 'between';

    protected $startDate = 'startDate';
    protected $endDate = 'endDate';

    /**
     * do Compare
     *
     * @param mixed $value
     * @param mixed $conditionComparator
     * @param mixed $conditionValue
     * @return bool false if not matched
     * @throws \Exception
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
                if (is_array($conditionValue)) {
                    foreach ($conditionValue as $compare) {
                        if (strpos($value, $compare) !== false) {
                            return true;
                        }
                    }

                    return false;
                }

                return strpos($value, $conditionValue) !== false;
            case $this->conditionComparisonValueNotContain:
                if (is_array($conditionValue)) {
                    foreach ($conditionValue as $compare) {
                        if (strpos($value, $compare) !== false) {
                            return false;
                        }
                    }

                    return true;
                }

                return strpos($value, $conditionValue) === false;
            case $this->conditionComparisonValueBetween:
                if (!array_key_exists($this->startDate, $conditionValue) ||
                    !array_key_exists($this->endDate, $conditionValue)
                ) {
                    throw new \Exception('Missing startDate, endDate for Between Expression');
                }

                $startDate = date_create($conditionValue[$this->startDate]);
                $endDate = date_create($conditionValue[$this->endDate]);

                $date = date_create($value);

                if (!$startDate instanceof \DateTime ||
                    !$endDate instanceof \DateTime ||
                    !$date instanceof \DateTime
                ) {
                    return false;
                }

                return $date >= $startDate && $date <= $endDate;
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