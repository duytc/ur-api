<?php


namespace UR\Domain\DTO\Report\Filters;


class TextFilter extends AbstractFilter
{

    const COMPARISON_TYPE_EQUAL = 1;
    const COMPARISON_TYPE_NOT_EQUAL = 2;
    const COMPARISON_TYPE_CONTAINS = 3;
    const COMPARISON_TYPE_START_WITH = 4;

    protected $comparisonType;

    protected $comparisonValue;

    /**
     * TextFilter constructor.
     * @param $fieldName
     * @param $fieldType
     * @param $comparisonType
     * @param $comparisonValue
     */
    public function __construct($fieldName, $fieldType, $comparisonType, $comparisonValue)
    {
        $this->fieldName = $fieldName;
        $this->fieldType = $fieldType;
        $this->comparisonType = $comparisonType;
        $this->comparisonValue = $comparisonValue;
    }

    /**
     * @return mixed
     */
    public function getComparisonType()
    {
        return $this->comparisonType;
    }

    /**
     * @return mixed
     */
    public function getComparisonValue()
    {
        return $this->comparisonValue;
    }
}