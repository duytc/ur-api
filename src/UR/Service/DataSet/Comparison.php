<?php

namespace UR\Service\DataSet;

final class Comparison
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

    public static function isValidNumberComparison($name)
    {
        return in_array($name, self::$comparisonForNumbers, true);
    }

    public static function isValidTextComparison($name)
    {
        return in_array($name, self::$comparisonForTexts, true);
    }
}