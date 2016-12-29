<?php

namespace UR\Service\DataSource;


final class DataSourceType
{
    const DS_EXCEL_FORMAT = 'excel';
    const DS_CSV_FORMAT = 'csv';
    const DS_JSON_FORMAT = 'json';

    public static $EXCEL_TYPES = ['xls', 'xlsx'];
    public static $JSON_TYPES = ['json'];
    public static $CSV_TYPES = ['csv'];

    private static $SUPPORTED_DS_FORMAT = [self::DS_EXCEL_FORMAT, self::DS_CSV_FORMAT, self::DS_JSON_FORMAT];

    public static function isExcelType($type)
    {
        return in_array($type, DataSourceType::$EXCEL_TYPES);
    }

    public static function isCsvType($type)
    {
        return in_array($type, DataSourceType::$CSV_TYPES);
    }

    public static function isJsonType($type)
    {
        return in_array($type, DataSourceType::$JSON_TYPES);
    }
}