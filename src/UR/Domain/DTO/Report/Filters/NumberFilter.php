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
    const COMPARISON_TYPE_NOT_IN = 'not in';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    public static $SUPPORTED_COMPARISON_TYPES = [
        self::COMPARISON_TYPE_EQUAL,
        self::COMPARISON_TYPE_SMALLER,
        self::COMPARISON_TYPE_SMALLER_OR_EQUAL,
        self::COMPARISON_TYPE_GREATER,
        self::COMPARISON_TYPE_GREATER_OR_EQUAL,
        self::COMPARISON_TYPE_NOT_EQUAL,
        self::COMPARISON_TYPE_IN,
        self::COMPARISON_TYPE_NOT_IN
    ];

    /** @var string */
    protected $comparisonType;

    /** @var string|array due to comparisonType */
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
            throw new \Exception(sprintf('Either parameters: %s, %s, %s, %s does not exits in text filter',
                self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
        }

        $this->fieldName = $numberFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $numberFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->comparisonType = $numberFilter[self::COMPARISON_TYPE_FILTER_KEY];
        $this->comparisonValue = $numberFilter[self::COMPARISON_VALUE_FILTER_KEY];

        // validate comparisonType
        $this->validateComparisonType();

        // validate comparisonValue
        $this->validateComparisonValue();
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

    /**
     * validate ComparisonType
     *
     * @throws \Exception
     */
    private function validateComparisonType()
    {
        if (!in_array($this->comparisonType, self::$SUPPORTED_COMPARISON_TYPES)) {
            throw new \Exception(sprintf('Not supported comparisonType %s', $this->comparisonType));
        }
    }

    /**
     * validate ComparisonValue
     *
     * @throws \Exception
     */
    private function validateComparisonValue()
    {
        // expect array
        if ($this->comparisonType == self::COMPARISON_TYPE_IN
            || $this->comparisonType == self::COMPARISON_TYPE_NOT_IN
        ) {
            if (!is_array($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType %s', $this->comparisonType));
            }

            foreach ($this->comparisonValue as $cv) {
                if (!is_numeric($cv)) {
                    throw new \Exception(sprintf('Expect comparisonValue is array of numeric with comparisonType %s', $this->comparisonType));
                }
            }
        } else {
            // expect single value
            if (!is_numeric($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is numeric with comparisonType %s', $this->comparisonType));
            }
        }
    }
}