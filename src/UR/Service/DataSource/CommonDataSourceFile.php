<?php

namespace UR\Service\DataSource;


Abstract class CommonDataSourceFile
{
    protected function removeInvalidColumns(array $arr)
    {
        foreach ($arr as $key => $value) {
            if ($value === null || $value === '') {
                unset($arr[$key]);
            }
        }

        return $arr;
    }

    /**
     * trim all trailing columns with empty value
     *
     * @param array $arr
     * @return array
     */
    protected function removeInvalidTrailingColumns(array $arr)
    {
        $foundIndex = false;

        // find the index that is trailing empty columns start point
        foreach ($arr as $index => $value) {
            if (!empty($value)) {
                $foundIndex = false;
                continue;
            }

            if (false === $foundIndex) {
                $foundIndex = $index;
            }
        }

        // unset all empty elements if found
        if (false !== $foundIndex) {
            foreach ($arr as $index => $value) {
                if (false !== $foundIndex && $index < $foundIndex) {
                    continue;
                }

                unset($arr[$index]);
            }
        }

        return $arr;
    }

    /**
     * set Default value for Columns of header that are empty
     *
     * @param array $header
     * @return array
     */
    protected function setDefaultColumnValueForHeader(array $header)
    {
        // append default column name for empty value
        foreach ($header as $index => &$value) {
            if (!empty($value)) {
                continue;
            }

            $value = sprintf('column_%d', ($index + 1));
        }

        return $header;
    }
}