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
}