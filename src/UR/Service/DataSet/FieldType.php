<?php

namespace UR\Service\DataSet;

use Doctrine\DBAL\Types\Type;

final class FieldType
{
    const DATE = 'date';
    const DATETIME = 'datetime';
    const TEXT = 'text'; // varchar
    const LARGE_TEXT = 'largeText'; // text
    const NUMBER = 'number'; // integer
    const DECIMAL = 'decimal'; // double/float
    const TEMPORARY = 'temporary';

    /** special for __unique_id: char instead of varchar */
    const DATABASE_TYPE_UNIQUE_ID = 'char';

    public static $types = [
        self::DATE,
        self::DATETIME,
        self::TEXT,
        self::LARGE_TEXT,
        self::NUMBER,
        self::DECIMAL
    ];

    public static $MAPPED_FIELD_TYPE_DBAL_TYPE = [
        self::DATE => Type::DATE,
        self::DATETIME => Type::DATETIME,
        self::TEXT => Type::STRING,
        self::LARGE_TEXT => Type::STRING,
        self::NUMBER => Type::INTEGER,
        self::DECIMAL => Type::DECIMAL
    ];

    public static $MAPPED_FIELD_TYPE_DATABASE_TYPE = [
        self::DATE => Type::DATE,
        self::DATETIME => Type::DATETIME,
        self::TEXT => 'varchar',
        self::LARGE_TEXT => 'varchar',
        self::NUMBER => Type::INTEGER,
        self::DECIMAL => Type::DECIMAL
    ];

    public static function isValidType($name)
    {
        return in_array($name, self::$types, true);
    }
}