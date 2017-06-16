<?php


namespace UR\Util;


trait ScientificValueTrait
{
    /**
     * @param $originalValue
     * @return mixed
     */
    public function normalizeScientificValue($originalValue)
    {
        $value = $originalValue;

        /** Return if string */
        if (!is_numeric($value)) {
            return $value;
        }

        /** Convert scientific format as 1E-5 */
        if (preg_match('/([0-9.+]+)([Ee])([+\-0-9]+)/', $value, $matches)) {
            $value = number_format($value, abs($matches[3]));
        }

        /** If convert fail, return original value from cell*/
        if (!$value) {
            $value = $originalValue;
        }

        return $value;
    }
}