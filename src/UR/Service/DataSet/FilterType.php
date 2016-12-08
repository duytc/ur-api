<?php

namespace UR\Service\DataSet;

final class FilterType
{
    const TYPE = 'type';
    const FIELD='field';
    const COMPARISON = 'comparison';
    const COMPARE_VALUE = 'compareValue';
    const SMALLER = 'smaller';
    const SMALLER_OR_EQUAL = 'smaller or equal';
    const EQUAL = 'equal'; // varchar
    const NOT_EQUAL = 'not equal'; // text
    const GREATER = 'greater'; // integer
    const GREATER_OR_EQUAL = 'greater or equal'; // double/float
    const IN = 'in';
    const NOT_IN = 'not in';

    const FORMAT = 'format';
    const FROM = 'startDate';
    const TO = 'endDate';

    const CONTAINS = 'contains';
    const NOT_CONTAINS = 'not contains';
    const START_WITH = 'start with';
    const END_WITH = 'end with';
    const DEFAULT_DATE_FORMAT= '!Y-m-d';


    private static $comparisonForNumbers = [
        self::SMALLER,
        self::SMALLER_OR_EQUAL,
        self::EQUAL,
        self::NOT_EQUAL,
        self::GREATER,
        self::GREATER_OR_EQUAL,
        self::IN,
        self::NOT_IN
    ];

    private static $comparisonForTexts = [
        self::CONTAINS,
        self::NOT_CONTAINS,
        self::START_WITH,
        self::END_WITH,
        self::IN,
        self::NOT_IN
    ];

    /**
     * check if is Valid FilterDate Type
     *
     * @param array $arr
     * @return bool
     */
    public static function isValidFilterDateType(array $arr)
    {
        if (count($arr) !== 5 || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr) || !array_key_exists(self::FORMAT, $arr)) {
            return false;
        }

        $dateFrom = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $arr[self::FROM]);
        $dateTo = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $arr[self::TO]);

        if (!$dateFrom || !$dateTo) {
            return false;
        }

        //todo check date range is valid
        return true;
    }

    /**
     * check if is Valid FilterNumber Type
     *
     * @param array $arr
     * @return bool
     */
    public static function isValidFilterNumberType(array $arr)
    {
        if (count($arr) !== 4 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForNumbers, true)) {
            return false;
        }

        // validate compareValue is array for special cases
        if (self::COMPARISON == self::IN || self::COMPARISON == self::NOT_IN) {
            if (!is_array($arr[self::COMPARE_VALUE])) {
                return false;
            }
        }

        return true;
    }

    /**
     * check if is Valid FilterText Type
     *
     * @param array $arr
     * @return bool
     */
    public static function isValidFilterTextType(array $arr)
    {
        if (count($arr) !== 4 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForTexts, true)) {
            return false;
        }

        // validate compareValue is array
        if (!is_array($arr[self::COMPARE_VALUE])) {
            return false;
        }

        return true;
    }
}