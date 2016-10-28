<?php

namespace UR\Service\DataSet;

final class TransformType
{

    const TYPE = 'type';
    const TO = 'to';
    const DATE = 'date';
    const NUMBER = 'number';
    const GROUP_BY = 'groupBy';
    const SORT_BY = 'sortBy';
    const ADD_FIELD = 'addField';
    const ADD_CALCULATED_FIELD = 'addCalculatedField';
    const COMPARISON_PERCENT = 'comparisonPercent';

    private static $transformTypes = [
        self::GROUP_BY,
        self::SORT_BY,
        self::ADD_FIELD,
        self::ADD_CALCULATED_FIELD,
        self::COMPARISON_PERCENT
    ];

    private static $groupSortTypes = [
        self::GROUP_BY,
        self::SORT_BY
    ];

    private static $addTypes = [
        self::ADD_FIELD,
        self::ADD_CALCULATED_FIELD,
        self::COMPARISON_PERCENT
    ];

    private static $singleTransformTypes = [
        self::DATE,
        self::NUMBER
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
    ];

    private static $supportedNumberFormats = [
        '####.#', // 1234.599 -> 1234.6
        '#.000', // 1234 -> 1234.000
        '0.#', // 0.631 -> 0.6
        '#.0#' // 12 -> 12.0  ; 1234.5678 -> 1234.57
    ];

    public static function isValidAllFieldTransformType($name)
    {
        return in_array($name, self::$transformTypes, true);
    }

    public static function isValidSingleFieldTransformType($arr)
    {
        if (!array_key_exists(self::TYPE, $arr) || !array_key_exists(self::TO, $arr)) {
            return false;
        }

        if (!in_array($arr[self::TYPE], self::$singleTransformTypes)) {
            return false;
        }

        if ($arr[self::TYPE] === self::DATE) {

            if (!in_array($arr[self::TO], self::$supportedDateFormats)) {
                return false;
            }
        }

        if ($arr[self::TYPE] === self::NUMBER) {

            if (!in_array($arr[self::TO], self::$supportedNumberFormats)) {
                return false;
            }
        }

        return true;
    }

    public static function isGroupOrSortType($type)
    {
        return in_array($type, self::$groupSortTypes);
    }

    public static function isAddingType($type)
    {
        return in_array($type, self::$addTypes);
    }

}