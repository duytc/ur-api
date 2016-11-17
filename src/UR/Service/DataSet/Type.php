<?php

namespace UR\Service\DataSet;

final class Type
{
    const PREFIX_DATA_IMPORT_TABLE = '__data_import_%d';
    const DATE = 'date';
    const DATETIME = 'datetime';
    const TEXT = 'text'; // varchar
    const MULTI_LINE_TEXT = 'multiLineText'; // text
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

    public static function isTransformSingleField(array $transform)
    {
        if (strcmp($transform[TransformType::TRANSFORM_TYPE], Type::SINGLE_FIELD) === 0) {
            return true;
        }
        return false;
    }

    public static function isTransformAllField(array $transform)
    {
        if (strcmp($transform[TransformType::TRANSFORM_TYPE], Type::ALL_FIELD) === 0) {
            return true;
        }
        return false;
    }
}