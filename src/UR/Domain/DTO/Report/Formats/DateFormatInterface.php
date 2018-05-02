<?php


namespace UR\Domain\DTO\Report\Formats;


interface DateFormatInterface extends FormatInterface
{
    const DEFAULT_DATE_FORMAT = 'Y-m-d';
    
    /**
     * @return mixed
     */
    public function getOutputFormat();
}