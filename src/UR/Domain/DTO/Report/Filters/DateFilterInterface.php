<?php


namespace UR\Domain\DTO\Report\Filters;


interface DateFilterInterface extends FilterInterface
{
    /**
     * @return string
     */
    public function getDateFormat();

    /**
     * @return mixed
     */
    public function getEndDate();

    /**
     * @return array
     */
    public function getStartDate();
}