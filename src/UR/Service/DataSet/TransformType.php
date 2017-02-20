<?php

namespace UR\Service\DataSet;

final class TransformType
{
    const TYPE = 'type';
    const FROM = 'from';
    const TO = 'to';
    const DATE = 'date';
    const IS_CUSTOM_FORMAT_DATE_FROM = 'isCustomFormatDateFrom';
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
    const REPLACE_TEXT = 'replaceText';
    const EXTRACT_PATTERN = 'extractPattern';
    const EXPRESSION = 'expression';
    const NAMES = 'names';
    const DIRECTION = 'direction';
    const NUMERATOR = 'numerator';
    const DENOMINATOR = 'denominator';
    const SEARCH_FOR = 'searchFor';
    const REG_EXPRESSION = 'searchPattern';
    const IS_REG_EXPRESSION_CASE_INSENSITIVE = 'isCaseInsensitive';
    const IS_REG_EXPRESSION_MULTI_LINE = 'isMultiLine';
    const MATCHED_POSITION = 'matchedPosition';
    const POSITION = 'position';
    const REPLACE_WITH = 'replaceWith';
    const TARGET_FIELD = 'targetField';
    const IS_OVERRIDE = 'isOverride';
    const DATE_FORMAT = 'dateFormat';
    const FILE_NAME = '[__filename]';
    const EMAIL_SUBJECT = '[__email_subject]';
    const EMAIL_BODY = '[__email_body]';
    const EMAIL_DATE_TIME = '[__email_date_time]';

    public static $internalFields = [
        self::FILE_NAME,
        self::EMAIL_SUBJECT,
        self::EMAIL_BODY,
        self::EMAIL_DATE_TIME
    ];

    public static $transformTypes = [
        self::DATE,
        self::NUMBER,
        self::GROUP_BY,
        self::SORT_BY,
        self::ADD_FIELD,
        self::ADD_CALCULATED_FIELD,
        self::ADD_CONCATENATED_FIELD,
        self::COMPARISON_PERCENT,
        self::ADD_CONCATENATED_FIELD,
        self::REPLACE_TEXT,
        self::EXTRACT_PATTERN
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
        'M d, Y', // Mar 01,2016
        'Y, M d', // 2016, Mar 01

        /** PHP Excel support 2 digits for year */
        'd-m-y',  // 15-01-99
        'd/m/y',  // 15/01/99
        'm-d-y',  // 01-15-99
        'm/d/y',  // 01/15/99
        'y-m-d',  // 99-01-15
        'y/m/d',  // 99/01/15
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
            // TODO: remove this hard coded checking
            // if (count($arr) !== 5 || !array_key_exists(self::FIELD, $arr) || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr)) {
            if (!array_key_exists(self::FIELD, $arr) || !array_key_exists(self::FROM, $arr) || !array_key_exists(self::TO, $arr)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] missing config information";
            }

            // validate format of date from
            $isCustomFormatDateFrom = !array_key_exists(self::IS_CUSTOM_FORMAT_DATE_FROM, $arr) ? false : (bool)$arr[self::IS_CUSTOM_FORMAT_DATE_FROM];
            if (!$isCustomFormatDateFrom && !in_array($arr[self::FROM], self::$supportedDateFormats)) {
                // validate using builtin data formats (when isCustomFormatDateFrom = false)
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] not support \"from\" date format";
            }

            if (!in_array($arr[self::TO], self::$supportedDateFormats)) {
                return "Transform setting error: field [" . $arr[TransformType::FIELD] . "] not support \"to\" date format";
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