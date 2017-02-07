<?php
/**
 * Created by PhpStorm.
 * User: linhvu
 * Date: 03/02/2017
 * Time: 14:50
 */

namespace UR\Service\DataSource;


Abstract class CommonFile
{
    protected function validValue(array $arr)
    {
        foreach ($arr as $key => $value) {
            if ($value === null || $value === '') {
                unset($arr[$key]);
            }
        }

        return $arr;
    }
}