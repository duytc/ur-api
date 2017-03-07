<?php

namespace UR\Service\Parser\Filter;

interface ColumnFilterInterface
{
    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const DATE = 'date';
    const NUMBER = 'number';
    const TEXT = 'text';

    /**
     * @param $value
     * @return bool|int true if passed, false if not passed or an int code if other error
     */
    public function filter($value);

    public function validate();
}