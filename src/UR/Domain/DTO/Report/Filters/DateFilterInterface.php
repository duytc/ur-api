<?php


namespace UR\Domain\DTO\Report\Filters;


interface DateFilterInterface extends AbstractFilterInterface
{
    /**
     * @return string
     */
    public function getDateFormat();

}