<?php

namespace UR\Service\DataSet;

use DateTime;
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

    public static function convertValue($originalValue, $type)
    {
        switch ($type) {
            case FieldType::DATE:
                return DateTime::createFromFormat('Y-m-d', $originalValue);
            case FieldType::DATETIME:
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $originalValue);
                if (!$dateTime) {
                    $dateTime = DateTime::createFromFormat('Y-m-d H:i', $originalValue);
                }
                if (!$dateTime) {
                    $dateTime = DateTime::createFromFormat('Y-m-d H', $originalValue);
                }
                return $dateTime;
            case FieldType::NUMBER:
                return is_numeric($originalValue) ? round($originalValue) : null;
            case FieldType::DECIMAL:
                return is_numeric($originalValue) ? doubleval($originalValue) : null;
            case FieldType::TEXT:
                return strval($originalValue);
            case FieldType::LARGE_TEXT:
                return strval($originalValue);
        }
        return $originalValue;
    }
}