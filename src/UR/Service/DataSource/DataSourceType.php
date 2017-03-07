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

    /**
     * @param $extension
     * @return bool
     */
    public static function isSupportedExtension($extension)
    {
        return
            self::isExcelType($extension) ||
            self::isCsvType($extension) ||
            self::isJsonType($extension);
    }

    /**
     * @param $extension
     * @return string
     */
    public static function getOriginalDataSourceType($extension)
    {
        if (self::isExcelType($extension)){
            return self::DS_EXCEL_FORMAT;
        }

        if (self::isCsvType($extension)){
            return self::DS_CSV_FORMAT;
        }

        if (self::isJsonType($extension)){
            return self::DS_JSON_FORMAT;
        }

        return "";
    }
}