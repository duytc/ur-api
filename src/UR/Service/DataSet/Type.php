<?php

namespace UR\Service\DataSet;

final class Type
{
    const DATE = 'date';
    const DATETIME = 'datetime';
    const TEXT = 'text'; // varchar
    const MULTI_LINE_TEXT = 'multi_line_text'; // text
    const NUMBER = 'number'; // integer
    const DECIMAL = 'decimal'; // double/float
    const SINGLE_FIELD = 'single-field';
    const ALL_FIELD = 'all-fields';

    private static $types = [
        self::DATE,
        self::DATETIME,
        self::TEXT,
        self::MULTI_LINE_TEXT,
        self::NUMBER,
        self::DECIMAL
    ];

    private static $filterTypes = [
        self::DATE,
        self::NUMBER,
        self::TEXT,
    ];

    private static $transformTypes = [
        self::SINGLE_FIELD,
        self::ALL_FIELD
    ];


    public static function isValidType($name)
    {
        return in_array($name, self::$types, true);
    }

    public static function isValidFilterType($name)
    {
        return in_array($name, self::$filterTypes, true);
    }

    public static function isValidTransformType($name)
    {
        return in_array($name, self::$transformTypes, true);
    }
}