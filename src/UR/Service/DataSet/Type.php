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