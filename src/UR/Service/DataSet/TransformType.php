<?php

namespace UR\Service\DataSet;

final class TransformType
{

    const TYPE = 'type';
    const FROM = 'from';
    const TO = 'to';
    const DATE = 'date';
    const NUMBER = 'number';
    const FIELD = 'field';
    const FIELDS = 'fields';
    const VALUE = 'value';
    const DECIMALS = 'decimals';
    const THOUSANDS_SEPARATOR = 'thousandsSeparator';
    const GROUP_BY = 'groupBy';
    const SORT_BY = 'sortBy';
    const ADD_FIELD = 'addField';
    const ADD_CALCULATED_FIELD = 'addCalculatedField';
    const COMPARISON = 'comparison';
    const COMPARISON_PERCENT = 'comparisonPercent';
    const EXPRESSION = 'expression';
    const NAMES = 'names';
    const DIRECTION = 'direction';
    const NUMERATOR = 'numerator';
    const DENOMINATOR = 'denominator';

    public static $transformTypes = [
        self::DATE,
        self::NUMBER,
        self::GROUP_BY,
        self::SORT_BY,
        self::ADD_FIELD,
        self::ADD_CALCULATED_FIELD,
        self::COMPARISON_PERCENT
    ];

    private static $dateOrNumberTransform = [
        self::DATE,
        self::NUMBER
    ];

    private static $supportedThousandsSeparator = [
        ",", "."
    ];

    private static $supportedDateFormats = [
        'Y-m-d',  // 2016-02-01
        'Y/m/d',  // 2016/02/01
        'Y-m-j',  // 2016-02-1
        'Y/m/j',  // 2016/02/1
        'Y-n-d',  // 2016-2-01
        'Y/n/d',  // 2016/2/01
        'Y-n-j',  // 2016-2-1
        'Y/n/j',  // 2016/2/1
        'm-d-Y',  // 02-01-2016
        'm/d/Y',  // 02/01/2016
        'm-j-Y',  // 02-1-2016
        'm/j/Y',  // 02/1/2016
        'n-d-Y',  // 2-01-2016
        'n/d/Y',  // 2/01/2016
        'n-j-Y',  // 2-1-2016
        'n/j/Y',  // 2/1/2016
        'd/m/Y',  // 2/1/2016
    ];

    public static function isDateOrNumberTransform($type)
    {
        return in_array($type, self::$dateOrNumberTransform, true);
    }

    public static function isValidTransformType($name)
    {
        return in_array($name, self::$transformTypes, true);
    }

    public static function isValidDateOrNumberTransform($arr)
    {
        if ($arr[self::TYPE] === self::DATE) {
            if (count($arr) !== 4 || !array_key_exists(self::FIELD, $arr) || !array_key_exists(self::TYPE, $arr) || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] missing config information";
            }

            if (!in_array($arr[self::TO], self::$supportedDateFormats) || !in_array($arr[self::FROM], self::$supportedDateFormats)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] not support date format";
            }
        }

        if ($arr[self::TYPE] === self::NUMBER) {
            if (count($arr) !== 4 || !array_key_exists(self::FIELD, $arr) || !array_key_exists(self::TYPE, $arr) || !array_key_exists(self::DECIMALS, $arr) || !array_key_exists(self::THOUSANDS_SEPARATOR, $arr)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] missing config information";
            }
            if (!is_numeric($arr[self::DECIMALS]) || !in_array($arr[self::THOUSANDS_SEPARATOR], self::$supportedThousandsSeparator)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] number config error";
            }
        }
        return 0;
    }
}