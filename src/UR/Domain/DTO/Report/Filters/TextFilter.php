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
    const COMPARISON_TYPE_NOT_IN = 'not in';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    public static $SUPPORTED_COMPARISON_TYPES = [
        self::COMPARISON_TYPE_EQUAL,
        self::COMPARISON_TYPE_NOT_EQUAL,
        self::COMPARISON_TYPE_CONTAINS,
        self::COMPARISON_TYPE_NOT_CONTAINS,
        self::COMPARISON_TYPE_START_WITH,
        self::COMPARISON_TYPE_END_WITH,
        self::COMPARISON_TYPE_IN,
        self::COMPARISON_TYPE_NOT_IN
    ];

    /** @var string */
    protected $comparisonType;

    /** @var string|array due to comparisonType */
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
            throw new \Exception(sprintf('Either parameters: %s, %s, %s or %s does not exits in text filter',
                self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
        }

        $this->fieldName = $textFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $textFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->comparisonType = $textFilter[self::COMPARISON_TYPE_FILTER_KEY];
        $this->comparisonValue = $textFilter[self::COMPARISON_VALUE_FILTER_KEY];

        // validate comparisonType
        $this->validateComparisonType();

        // validate comparisonValue
        $this->validateComparisonValue();
    }

    /**
     * @inheritdoc
     */
    public function getComparisonType()
    {
        return $this->comparisonType;
    }

    /**
     * @inheritdoc
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
        if ($this->comparisonType == self::COMPARISON_TYPE_CONTAINS
            || $this->comparisonType == self::COMPARISON_TYPE_NOT_CONTAINS
            || $this->comparisonType == self::COMPARISON_TYPE_START_WITH
            || $this->comparisonType == self::COMPARISON_TYPE_END_WITH
            || $this->comparisonType == self::COMPARISON_TYPE_IN
            || $this->comparisonType == self::COMPARISON_TYPE_NOT_IN
        ) {
            if (!is_array($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType %s, got %s', $this->comparisonType, $this->comparisonValue));
            }
        }
    }
}