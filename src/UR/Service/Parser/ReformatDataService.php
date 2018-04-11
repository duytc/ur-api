<?php

namespace UR\Service\Parser;


use UR\Service\DataSet\FieldType;

class ReformatDataService
{
    /**
     * @param $cellValue
     * @param string $type
     * @return mixed|null
     */
    public function reformatData($cellValue, $type)
    {
        switch ($type) {
            case FieldType::DECIMAL:
            case FieldType::NUMBER:
                $cellValue = number_format($cellValue, 15);
                $cellValue = rtrim(rtrim(strval($cellValue), "0"), ".");
                $cellValue = preg_replace('/[^\d.-]+/', '', $cellValue);

                // advance process on dash character
                // if dash is at first position => negative flag
                // else => remove dash
                $firstNegativePosition = strpos($cellValue, '-');
                if ($firstNegativePosition === 0) {
                    $afterFirstNegative = substr($cellValue, 1);
                    $afterFirstNegative = preg_replace('/\-{1,}/', '', $afterFirstNegative);
                    $cellValue = '-' . $afterFirstNegative;
                } else if ($firstNegativePosition > 0) {
                    $cellValue = preg_replace('/\-{1,}/', '', $cellValue);
                }

                // advance process on dot character
                // if dash is at first position => append 0
                // else => remove dot
                $firstDotPosition = strpos($cellValue, '.');
                if ($firstDotPosition !== false) {
                    $first = substr($cellValue, 0, $firstDotPosition);
                    if (!is_numeric($first)) {
                        $first = '0';
                    }

                    $second = substr($cellValue, $firstDotPosition + 1);
                    $second = preg_replace('/\.{1,}/', '', $second);
                    $cellValue = $first . '.' . $second;
                }

                if (!is_numeric($cellValue)) {
                    $cellValue = null;
                }

                break;

            case FieldType::DATE:
            case FieldType::DATETIME:
                // the cellValue may be a DateTime instance if file type is excel. The object is return by excel reader library
                if ($cellValue instanceof \DateTime) {
                    break;
                }

                // make sure date value contain number,
                // else the value is invalid, then we return 'null' for the date transformer removes entire row due to date null
                // e.g:
                // "1/21/17" is valid, "Jan 21 17" is valid,
                // "Jan abc21 17" is valid (but when date transformer creates date, it will be invalid),
                // "total" is invalid date, so we return null, then date transformer remove entire row contains this date
                if (!preg_match('/[\d]+/', $cellValue)) {
                    $cellValue = null;
                }

                break;

            case FieldType::TEXT:
            case FieldType::LARGE_TEXT:
                $cellValue = trim($cellValue);

                if ($cellValue === '') {
                    return null; // treat empty string as null value
                }

                break;

            default:
                break;
        }

        return $cellValue;
    }
}