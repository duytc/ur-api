<?php


namespace UR\Domain\DTO\Report\Filters;


class TextFilter extends AbstractFilter
{

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