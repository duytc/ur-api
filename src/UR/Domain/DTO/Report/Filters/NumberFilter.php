<?php


namespace UR\Domain\DTO\Report\Filters;


class NumberFilter extends AbstractFilter implements NumberFilterInterface
{
    const COMPARISON_TYPE_EQUAL = 'equal';
    const COMPARISON_TYPE_SMALLER = 'smaller';
    const COMPARISON_TYPE_SMALLER_OR_EQUAL = 'smaller or equal';
    const COMPARISON_TYPE_GREATER = 'greater';
    const COMPARISON_TYPE_GREATER_OR_EQUAL = 'greater or equal';
    const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
    const COMPARISON_TYPE_IN = 'in';
    const COMPARISON_TYPE_NOT = 'not in';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    protected $comparisonType;

    protected $comparisonValue;

    /**
     * @param array $numberFilter
     * @throws \Exception
     */
    public function __construct(array $numberFilter)
    {
        if (!array_key_exists(self::FILED_NAME_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::FIELD_TYPE_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::COMPARISON_TYPE_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::COMPARISON_VALUE_FILTER_KEY, $numberFilter)
        ) {
            throw new \Exception(sprintf('Either parameters: %s, %s, %s, %s, %s does not exits in text filter',
                self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
        }

        $this->fieldName = $numberFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $numberFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->comparisonType = $numberFilter[self::COMPARISON_TYPE_FILTER_KEY];
        $this->comparisonValue = $numberFilter[self::COMPARISON_VALUE_FILTER_KEY];
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