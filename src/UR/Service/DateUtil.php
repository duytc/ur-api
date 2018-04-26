<?php

namespace UR\Service;

use DateTime;
use UR\Domain\DTO\Report\DateRange;

class DateUtil implements DateUtilInterface
{
    /**
     * @inheritdoc
     */
    public function getDateTime($dateString = null, $returnTodayIfEmpty = false)
    {
        if (empty($dateString)) {
            return $returnTodayIfEmpty ? new DateTime('today') : null;
        }

        if ($dateString instanceof DateTime) {
            return $dateString;
        }

        $validPattern = '#\d{4}-\d{2}-\d{2}#';

        if (!preg_match($validPattern, $dateString)) {
            throw new \Exception('dateString must be a date in the format YYMMDD');
        }

        $dateTime = DateTime::createFromFormat(self::DATE_FORMAT, $dateString);
        $dateTime->setTime(0, 0, 0);

        return $dateTime;
    }

    public function isTodayInRange(DateTime $startDate, DateTime $endDate)
    {
        $today = new DateTime('today');

        return $today >= $startDate && $today <= $endDate;
    }

    public function isDateBeforeToday(DateTime $date)
    {
        return $date < (new DateTime('today'));
    }

    public function formatDate(DateTime $date)
    {
        return $date->format(self::DATE_FORMAT);
    }

    public function getFirstDateInMonth(DateTime $date = null)
    {
        if (null === $date) {
            $date = new DateTime('today');
        }

        return new DateTime($date->format('1-m-Y'));
    }

    /**
     * @param DateTime $date
     * @param bool $forceEndOfMonth
     * @return DateTime end date of month this $date in when $forceEndOfMonth = true; otherwise return current $date
     */
    public function getLastDateInMonth(DateTime $date = null, $forceEndOfMonth = false)
    {
        if (null === $date) {
            $date = new DateTime('today');
        }

        $today = new DateTime('today');
        if ($today->format('m') === $date->format('m') && $forceEndOfMonth === false) {
            return $date;
        }

        return new DateTime($date->format('t-m-Y'));
    }

    /**
     * @return int
     */
    public function getNumberOfRemainingDatesInMonth()
    {
        $lastDate = $this->getLastDateInMonth(new DateTime('today'), true);

        return $lastDate->diff(new DateTime('today'))->days;
    }

    /**
     * @return int
     */
    public function getNumberOfDatesPassedInMonth()
    {
        $today = new DateTime('today');
        return $today->diff($this->getFirstDateInMonth())->days;
    }

    /**
     * @return bool
     */
    public function isFirstDateOfMonth()
    {
        return $this->getNumberOfDatesPassedInMonth() === 0;
    }

    public function mergeDateRange($dateRanges)
    {
        if ($dateRanges instanceof DateRange) {
            return $dateRanges;
        }

        if (empty($dateRanges)) {
            return null;
        }

        if (count($dateRanges) == 1) {
            return $dateRanges[0];
        }

        $startDates = array_map(function(DateRange $range) {
            return $range->getStartDate();
        }, $dateRanges);

        $endDates = array_map(function(DateRange $range) {
            return $range->getEndDate();
        }, $dateRanges);

        return new DateRange(min($startDates), max($endDates));
    }

    public function getDynamicDateRange($dynamicDateRange)
    {
        $startDate = '';
        $endDate = '';

        if (!is_string($dynamicDateRange)) {
            return ['' . self::START_DATE_KEY . '' =>$startDate, '' . self::END_DATE_KEY . '' => $endDate];
        }

        switch ($dynamicDateRange) {
            case self::DATE_DYNAMIC_VALUE_EVERYTHING:
                $startDate = $endDate = '';
                break;

            case self::DATE_DYNAMIC_VALUE_12_HOURS:
                $startDate = date('Y-m-d H:i:s', strtotime('-12 hours'));
                $endDate = date('Y-m-d H:i:s', strtotime('now'));
                break;

            case self::DATE_DYNAMIC_VALUE_24_HOURS:
                $startDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
                $endDate = date('Y-m-d H:i:s', strtotime('now'));
                break;

            case self::DATE_DYNAMIC_VALUE_TODAY:
                $startDate = date_create('now')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date_create('now')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_YESTERDAY:
                $startDate = date_create('yesterday')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date_create('yesterday')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_LAST_7_DAYS:
                $startDate = date_create('-7 day')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date_create('yesterday')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_LAST_30_DAYS:
                $startDate = date_create('-30 day')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date_create('yesterday')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_THIS_MONTH:
                $startDate = date('Y-m-01', strtotime('this month'));
                $startDate = date_create($startDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date_create('now')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_LAST_MONTH:
                $startDate = date('Y-m-01', strtotime('last month'));
                $startDate = date_create($startDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');

                $endDate = date('Y-m-t', strtotime('last month'));
                $endDate = date_create($endDate)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_LAST_2_MONTH:
                $month = time();
                $months = [];
                for ($i = 1; $i <= 2; $i++) {
                    $month = strtotime('last month', $month);
                    $months[] = $month;
                }
                $startDate = date('Y-m-01', $months[count($months) - 1]);
                $startDate = date_create($startDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date('Y-m-t', $months[0]);
                $endDate = date_create($endDate)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            case self::DATE_DYNAMIC_VALUE_LAST_3_MONTH:
                $month = time();
                $months = [];
                for ($i = 1; $i <= 3; $i++) {
                    $month = strtotime('last month', $month);
                    $months[] = $month;
                }
                $startDate = date('Y-m-01', $months[count($months) - 1]);
                $startDate = date_create($startDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $endDate = date('Y-m-t', $months[0]);
                $endDate = date_create($endDate)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                break;

            default:
                break;
        }

        return [self::START_DATE_KEY =>$startDate, self::END_DATE_KEY => $endDate];

    }
}