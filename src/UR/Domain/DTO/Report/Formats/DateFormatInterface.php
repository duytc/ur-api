<?php


namespace UR\Domain\DTO\Report\Formats;


interface DateFormatInterface extends FormatInterface
{
    /**
     * @return mixed
     */
    public function getOutputFormat();
}