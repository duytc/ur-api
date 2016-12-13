<?php

namespace UR\Service;

use DateTime;
use UR\Domain\DTO\Report\DateRange;
use UR\Exception\Report\InvalidDateException;

interface DateUtilInterface
{
    /**
     * Get a DateTime object
     * If $date is null, today's date is returned
     * The date format is Y-m-d i.e 2014-10-04
     *
     * @param int|DateTime|null $dateString
     * @param bool $returnTodayIfEmpty
     * @return DateTime
     * @throws InvalidDateException when an incorrect date format is supplied
     */
    public function getDateTime($dateString = null, $returnTodayIfEmpty = false);

    public function isTodayInRange(DateTime $startDate, DateTime $endDate);

    public function isDateBeforeToday(DateTime $date);

    public function formatDate(DateTime $date);

    public function getFirstDateInMonth(DateTime $date = null);

    /**
     * @param DateTime $date
     * @param bool $forceEndOfMonth
     * @return DateTime end date of month this $date in when $forceEndOfMonth = true; otherwise return current $date
     */
    public function getLastDateInMonth(DateTime $date = null, $forceEndOfMonth = false);

    /**
     * @return bool
     */
    public function isFirstDateOfMonth();

    /**
     * @return int
     */
    public function getNumberOfRemainingDatesInMonth();

    /**
     * @return int
     */
    public function getNumberOfDatesPassedInMonth();

    /**
     * @param $dateRanges
     * @return DateRange|null|array
     */
    public function mergeDateRange($dateRanges);
}