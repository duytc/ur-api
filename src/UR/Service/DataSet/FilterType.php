<?php

namespace UR\Service\DataSet;

final class FilterType
{
    const TYPE = 'type';
    const COMPARISON = 'comparison';
    const COMPARE_VALUE = 'compareValue';
    const SMALLER = 'smaller';
    const SMALLER_OR_EQUAL = 'smaller or equal';
    const EQUAL = 'equal'; // varchar
    const NOT_EQUAL = 'not equal'; // text
    const GREATER = 'greater'; // integer
    const GREATER_OR_EQUAL = 'greater or equal'; // double/float
    const IN = 'in';
    const NOT = 'not';
    const FIELD='field';
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
        self::NOT
    ];

    private static $comparisonForTexts = [
        self::CONTAINS,
        self::NOT_CONTAINS,
        self::START_WITH,
        self::END_WITH,
        self::IN,
        self::NOT
    ];

    public static function isValidFilterDateType($arr)
    {
        if (count($arr) !== 5 || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr) || !array_key_exists(self::FORMAT, $arr)) {
            return false;
        }
        $dateFrom = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $arr[self::FROM]);
        $dateTo = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $arr[self::FROM]);

        if (!$dateFrom || !$dateTo) {
            return false;
        }
        //todo check date range is valid
        return true;
    }

    public static function isValidFilterNumberType($arr)
    {
        if (count($arr) !== 4 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForNumbers, true)) {
            return false;
        }

        return true;
    }

    public static function isValidFilterTextType($arr)
    {
        if (count($arr) !== 4 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForTexts, true)) {
            return false;
        }

        return true;
    }
}