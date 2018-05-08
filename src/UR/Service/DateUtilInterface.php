<?php

namespace UR\Service;

use DateTime;
use UR\Domain\DTO\Report\DateRange;

interface DateUtilInterface
{
    const DATE_FORMAT = 'Y-m-d';
    const DATE_FORMAT_HOURS = 'Y-m-d H';
    const END_DATE_KEY = 'endDate';
    const START_DATE_KEY = 'startDate';

    const DATE_DYNAMIC_VALUE_EVERYTHING= 'Everything';
    const DATE_DYNAMIC_VALUE_12_HOURS = '-12 hours';
    const DATE_DYNAMIC_VALUE_24_HOURS = '-24 hours';
    const DATE_DYNAMIC_VALUE_48_HOURS = '-48 hours';
    const DATE_DYNAMIC_VALUE_TODAY = 'today';
    const DATE_DYNAMIC_VALUE_YESTERDAY = 'yesterday';
    const DATE_DYNAMIC_VALUE_LAST_7_DAYS = 'last 7 days';
    const DATE_DYNAMIC_VALUE_LAST_30_DAYS = 'last 30 days';
    const DATE_DYNAMIC_VALUE_THIS_MONTH = 'this month';
    const DATE_DYNAMIC_VALUE_LAST_MONTH = 'last month';
    const DATE_DYNAMIC_VALUE_LAST_2_MONTH = 'last 2 months';
    const DATE_DYNAMIC_VALUE_LAST_3_MONTH = 'last 3 months';
    /**
     * Get a DateTime object
     * If $date is null, today's date is returned
     * The date format is Y-m-d i.e 2014-10-04
     *
     * @param int|DateTime|null $dateString
     * @param bool $returnTodayIfEmpty
     * @return DateTime
     * @throws \Exception when an incorrect date format is supplied
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

    public function getDynamicDateRange($dynamicDateRange);
}