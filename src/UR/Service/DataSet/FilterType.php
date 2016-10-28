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
    const FROM = 'from';
    const TO = 'to';

    const CONTAINS = 'contains';
    const NOT_CONTAINS = 'not contains';
    const START_WITH = 'start with';
    const END_WITH = 'end with';


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
        if (count($arr) !== 3 || !array_key_exists("from", $arr) || !array_key_exists("to", $arr)) {
            return false;
        }

        return true;
    }

    public static function isValidFilterNumberType($arr)
    {
        if (count($arr) !== 3 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForNumbers, true)) {
            return false;
        }

        return true;
    }

    public static function isValidFilterTextType($arr)
    {
        if (count($arr) !== 3 || !array_key_exists(self::COMPARISON, $arr) || !array_key_exists(self::COMPARE_VALUE, $arr)) {
            return false;
        }

        if (!in_array($arr[self::COMPARISON], self::$comparisonForTexts, true)) {
            return false;
        }

        return true;
    }
}