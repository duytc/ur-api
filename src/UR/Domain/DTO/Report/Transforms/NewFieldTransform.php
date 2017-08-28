<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

abstract class NewFieldTransform extends AbstractTransform implements TransformInterface
{
    const EQUAL_OPERATOR = 'equal';
    const SMALLER_OPERATOR = 'smaller';
    const SMALLER_OR_EQUAL_OPERATOR = 'smaller or equal';
    const GREATER_OPERATOR = 'greater';
    const GREATER_OR_EQUAL_OPERATOR = 'greater or equal';
    const NOT_EQUAL_OPERATOR = 'not equal';
    const IN_OPERATOR = 'in';
    const NOT_IN_OPERATOR = 'not in';
    const IS_INVALID_OPERATOR = 'is invalid';
    const INVALID_VALUE = NULL;
    const CONTAIN_OPERATOR = 'contain';
    const NOT_CONTAIN_OPERATOR = 'not contain';
    const BETWEEN_OPERATOR = 'between';
    const CALCULATED_FIELD = '$$CALCULATED_VALUE$$';
    const FIELD_NAME_KEY = 'field';
    const TYPE_KEY = 'type';
    const START_DATE_KEY = 'startDate';
    const END_DATE_KEY = 'endDate';


    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $type;

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param array $outputJoinField
     * @return mixed
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!in_array($this->fieldName, $metrics)) {
            $metrics[] = $this->fieldName;
        }

        if (!in_array($this->fieldName, $columns)) {
            $columns[] = $this->fieldName;
            $types[$this->fieldName] = $this->type;
            $collection->setColumns($columns);
            $collection->setTypes($types);
        }
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     * @return self
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param $value
     * @param $conditionComparator
     * @param $conditionValue
     * @return bool
     * @throws \Exception
     */
    protected function matchCondition($value, $conditionComparator, $conditionValue)
    {
        switch ($conditionComparator) {
            case NewFieldTransform::IS_INVALID_OPERATOR:
                return $value == NewFieldTransform::INVALID_VALUE;
            case NewFieldTransform::IN_OPERATOR:
                return in_array($value, $conditionValue);
            case NewFieldTransform::NOT_IN_OPERATOR:
                return !in_array($value, $conditionValue);
            case NewFieldTransform::SMALLER_OPERATOR:
                return $value < $conditionValue;
            case NewFieldTransform::SMALLER_OR_EQUAL_OPERATOR:
                return $value <= $conditionValue;
            case NewFieldTransform::EQUAL_OPERATOR:
                return $value == $conditionValue;
            case NewFieldTransform::NOT_EQUAL_OPERATOR:
                return $value != $conditionValue;
            case NewFieldTransform::GREATER_OPERATOR:
                return $value > $conditionValue;
            case NewFieldTransform::GREATER_OR_EQUAL_OPERATOR:
                return $value >= $conditionValue;
            case NewFieldTransform::CONTAIN_OPERATOR:
                if (is_array($conditionValue)) {
                    foreach($conditionValue as $compare) {
                        if (strpos($value, $compare) !== false) {
                            return true;
                        }
                    }

                    return false;
                }

                return strpos($value, $conditionValue) !== false;
            case NewFieldTransform::NOT_CONTAIN_OPERATOR:
                if (is_array($conditionValue)) {
                    foreach($conditionValue as $compare) {
                        if (strpos($value, $compare) !== false) {
                            return false;
                        }
                    }

                    return true;
                }

                return strpos($value, $conditionValue) === false;

            case self::BETWEEN_OPERATOR:
                if (!array_key_exists(self::START_DATE_KEY, $conditionValue) ||
                    !array_key_exists(self::END_DATE_KEY, $conditionValue)
                ) {
                    throw new \Exception('Missing startDate, endDate for Between Expression');
                }

                $startDate = date_create($conditionValue[self::START_DATE_KEY]);
                $endDate = date_create($conditionValue[self::END_DATE_KEY]);
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