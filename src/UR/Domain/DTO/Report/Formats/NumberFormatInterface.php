<?php


namespace UR\Domain\DTO\Report\Formats;


interface NumberFormatInterface extends FormatInterface
{
    /**
     * @return mixed
     */
    public function getPrecision();

    /**
     * @return mixed
     */
    public function getThousandSeparator();
}