<?php

namespace UR\Service\Parser\Transformer\Column;

interface ColumnTransformerInterface
{
    const FIELD_KEY = 'field';
    const TYPE_KEY = 'type';
    const DATE_FORMAT = 'date';
    const NUMBER_FORMAT = 'number';

    public function transform($value);

    public function validate();
}