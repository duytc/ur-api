<?php


namespace UR\Domain\DTO\Report\Filters;


class TextFilter extends AbstractFilter
{
    const COMPARISON_TYPE_EQUAL = 1;
    const COMPARISON_TYPE_NOT_EQUAL = 2;
    const COMPARISON_TYPE_CONTAINS = 3;
    const COMPARISON_TYPE_START_WITH = 4;

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    protected $comparisonType;

    protected $comparisonValue;

    /**
     * @param array $textFilter
     * @throws \Exception
     */
    public function __construct(array $textFilter)
    {
        if (!array_key_exists(self::FILED_NAME_FILTER_KEY, $textFilter)
            || !array_key_exists(self::FIELD_TYPE_FILTER_KEY, $textFilter)
            || !array_key_exists(self::COMPARISON_TYPE_FILTER_KEY, $textFilter)
            || !array_key_exists(self::COMPARISON_VALUE_FILTER_KEY, $textFilter)
        ) {
            throw new \Exception(sprintf('Either parameters: %s, %s, %s, %s, %s does not exits in text filter',
                self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
        }

        $this->fieldName = $textFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $textFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->comparisonType = $textFilter[self::COMPARISON_TYPE_FILTER_KEY];
        $this->comparisonValue = $textFilter[self::COMPARISON_VALUE_FILTER_KEY];
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