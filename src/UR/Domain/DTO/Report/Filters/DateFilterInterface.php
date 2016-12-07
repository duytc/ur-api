<?php


namespace UR\Domain\DTO\Report\Filters;


use DateTime;

interface DateFilterInterface extends AbstractFilterInterface
{
    /**
     * @return string
     */
    public function getDateFormat();
    /**
     * @return DateTime
     */
    public function getEndDate();

    /**
     * @return DateTime
     */
    public function getStartDate();
}