<?php

namespace UR\Service\Parser\Filter;

interface   ColumnFilterInterface
{
    /**
     * @param $value
     * @return bool|int true if passed, false if not passed or an int code if other error
     */
    public function filter($value);
}