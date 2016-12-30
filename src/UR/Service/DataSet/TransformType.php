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
    const ADD_CONCATENATED_FIELD = 'addConcatenatedField';
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
        self::ADD_CONCATENATED_FIELD,
        self::COMPARISON_PERCENT,
        self::ADD_CONCATENATED_FIELD
    ];

    private static $dateOrNumberTransform = [
        self::DATE,
        self::NUMBER
    ];

    private static $supportedThousandsSeparator = [
        ",", "none"
    ];

    private static $supportedDateFormats = [
        'Y-m-d',  // 2016-01-15
        'Y/m/d',  // 2016/01/15
        'm-d-Y',  // 01-15-2016
        'm/d/Y',  // 01/15/2016
        'd-m-Y',  // 15/01/2016
        'd/m/Y',  // 15/01/2016
        'Y-M-d',  // 2016-Mar-01
        'Y/M/d',  // 2016/Mar/01
        'M-d-Y',  // Mar-01-2016
        'M/d/Y',  // Mar/01/2016
        'd-M-Y',  // 01-Mar-2016
        'd/M/Y',  // 01/Mar/2016
    ];

    public static function isDateOrNumberTransform($type)
    {
        return in_array($type, self::$dateOrNumberTransform, true);
    }

    public static function isValidTransformType($name)
    {
        return in_array($name, self::$transformTypes, true);
    }

    /**
     * validate date or number transform
     *
     * @param $arr
     * @return int|string
     */
    public static function isValidDateOrNumberTransform($arr)
    {
        if ($arr[self::TYPE] === self::DATE) {
            return self::isValidDateTransform($arr);
        }

        if ($arr[self::TYPE] === self::NUMBER) {
            return self::isValidNumberTransform($arr);
        }

        return 0;
    }

    /**
     * validate date transform
     *
     * @param $arr
     * @return int|string
     */
    public static function isValidDateTransform($arr)
    {
        if ($arr[self::TYPE] === self::DATE) {
            if (count($arr) !== 4 || !array_key_exists(self::FIELD, $arr) || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] missing config information";
            }

            if (!in_array($arr[self::TO], self::$supportedDateFormats) || !in_array($arr[self::FROM], self::$supportedDateFormats)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] not support date format";
            }
        }

        return 0;
    }

    /**
     * validate number transform
     *
     * @param $arr
     * @return int|string
     */
    public static function isValidNumberTransform($arr)
    {
        if ($arr[self::TYPE] === self::NUMBER) {
            if (count($arr) !== 4 || !array_key_exists(self::FIELD, $arr) || !array_key_exists(self::DECIMALS, $arr) || !array_key_exists(self::THOUSANDS_SEPARATOR, $arr)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] missing config information";
            }
            if (!is_numeric($arr[self::DECIMALS]) || !in_array($arr[self::THOUSANDS_SEPARATOR], self::$supportedThousandsSeparator)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] number config error";
            }
        }

        return 0;
    }
}