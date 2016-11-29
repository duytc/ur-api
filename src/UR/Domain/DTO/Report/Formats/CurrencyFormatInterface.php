<?php


namespace UR\Domain\DTO\Report\Formats;


interface CurrencyFormatInterface extends FormatInterface
{
    /**
     * @return mixed
     */
    public function getCurrency();
}