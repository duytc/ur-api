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
                //Remove special characters such as %, $, €, comma and space
                $cellValue = preg_replace('/[%|$|€|,| ]+/', '', $cellValue);
                //Sometime is_float() can not check scientific notation with E, so we convert to e
                $cellValue = preg_replace('/[E]+/', 'e', $cellValue);

                //Check scientific notation here
                if (!is_float($cellValue) && !is_numeric($cellValue)) {
                    //Number with invalid data should be "Zero"
                    return 0;
                }

                //Auto convert scientific value to decimal value
                $cellValue = (float)$cellValue;
                $cellValue = $type == FieldType::NUMBER ? round($cellValue) : $cellValue;
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