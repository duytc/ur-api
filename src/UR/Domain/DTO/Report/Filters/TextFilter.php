<?php


namespace UR\Domain\DTO\Report\Filters;


class TextFilter extends AbstractFilter implements TextFilterInterface
{
    const COMPARISON_TYPE_EQUAL = 'equal';
    const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
    const COMPARISON_TYPE_CONTAINS = 'contains';
    const COMPARISON_TYPE_NOT_CONTAINS = 'not contains';
    const COMPARISON_TYPE_START_WITH = 'start with';
    const COMPARISON_TYPE_END_WITH = 'end with';
    const COMPARISON_TYPE_IN = 'in';
    const COMPARISON_TYPE_NOT = 'not in';

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

        if ($this->comparisonType == self::COMPARISON_TYPE_IN
            || $this->comparisonType == self::COMPARISON_TYPE_NOT
        ) {
            $this->comparisonValue = implode(",", $this->makeQuote($textFilter[self::COMPARISON_VALUE_FILTER_KEY]));
        } else {
            $this->comparisonValue = $textFilter[self::COMPARISON_VALUE_FILTER_KEY];
        }

    }

    /**
     * @param $str
     * @return array
     */
    protected function makeQuote($str)
    {
        $newString = [];
        $str = strpos($str,";") ? str_replace(';', ',', $str): $str;
        $subStrings = explode(',', $str);

        foreach ($subStrings as $subString) {
            $newSubString = '"' . trim($subString) . '"';
            $newString [] = $newSubString;
        }

        return $newString;
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