<?php

namespace UR\Service\DataSet;

final class FieldType
{
    const DATE = 'date';
    const DATETIME = 'datetime';
    const TEXT = 'text'; // varchar
    const MULTI_LINE_TEXT = 'multiLineText'; // text
    const NUMBER = 'number'; // integer
    const DECIMAL = 'decimal'; // double/float
    const TEMPORARY = 'temporary';

    private static $types = [
        self::DATE,
        self::DATETIME,
        self::TEXT,
        self::MULTI_LINE_TEXT,
        self::NUMBER,
        self::DECIMAL
    ];

    public static function isValidType($name)
    {
        return in_array($name, self::$types, true);
    }
}