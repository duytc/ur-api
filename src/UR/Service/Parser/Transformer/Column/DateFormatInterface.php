<?php


namespace UR\Service\Parser\Transformer\Column;


use UR\Service\Import\ImportDataException;

interface DateFormatInterface extends ColumnTransformerInterface
{
    /**
     * @param $value
     * @return \DateTime
     * @throws ImportDataException
     */
    public function getDate($value);
}