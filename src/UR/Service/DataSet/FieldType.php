<?php

namespace UR\Service\DataSet;

use DateTime;
use Doctrine\DBAL\Types\Type;
use UR\Service\Parser\Transformer\Column\DateFormat;

final class FieldType
{
    const DATE = 'date';
    const DATETIME = 'datetime';
    const TEXT = 'text'; // varchar
    const LARGE_TEXT = 'largeText'; // text
    const NUMBER = 'number'; // integer
    const DECIMAL = 'decimal'; // double/float
    const TEMPORARY = 'temporary';
    const FIELD = 'field';

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

    public static function convertValue($originalValue, $type , $fromDateFormats = null, $requireField = null)
    {
        switch ($type) {
            case FieldType::DATE:
            case FieldType::DATETIME:
                $resultDate = '';
                foreach ($fromDateFormats as $fromDateFormat){
                    if ($fromDateFormat[self::FIELD] == $requireField){
                        $fromValues = $fromDateFormat[DateFormat::FROM_KEY];
                        foreach ($fromValues as $from) {
                            $format = array_key_exists(DateFormat::FORMAT_KEY, $from) ? $from[DateFormat::FORMAT_KEY] : null;
                            if (array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $from) && $from[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM]) {
                                $format = DateFormat::convertCustomFromDateFormatToPHPDateFormat($format);
                            } else {
                                $format = DateFormat::convertDateFormatFullToPHPDateFormat($format);
                            }
                            $date = DateTime::createFromFormat('!' . $format, $originalValue); // auto set time (H,i,s) to 0 if not available

                            if (!$date instanceof DateTime) {
                                continue;
                            }
                            $resultDate = $date;
                        }
                    }
                }
                //This field is required and entered transform date
                if (!empty($resultDate)) {
                    return $resultDate;
                }
                //  This field is required but is not entered transform date -> remove this row
                else {
                    return false;
                }
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