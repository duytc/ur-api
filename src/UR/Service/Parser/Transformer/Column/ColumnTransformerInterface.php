<?php

namespace UR\Service\Parser\Transformer\Column;

use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Transformer\TransformerInterface;

interface ColumnTransformerInterface extends TransformerInterface
{
    const FIELD_KEY = 'field';
    const TYPE_KEY = 'type';
    const DATE_FORMAT = 'date';
    const NUMBER_FORMAT = 'number';

    /**
     * @param mixed $value
     * @return mixed
     * @throws \Exception|ImportDataException when $value invalid, e.g date wrong format
     */
    public function transform($value);

    /**
     * @return bool
     */
    public function validate();
}