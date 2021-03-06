<?php

namespace UR\Service\DateTime;

use UR\Model\Core\DataSourceIntegration;

class DateTimeUtil
{
    /**
     * DateTimeUtil constructor.
     */
    public function __construct()
    {

    }

    /**
     * @param \DateTime $lastExecuted
     * @param $checkValue
     * @return \DateTime
     */
    public function getNextExecutedByCheckEvery($lastExecuted, $checkValue)
    {
        /** Clone time on UTC */
        $nextExecuteAt = clone $lastExecuted;

        if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR, $checkValue)) {
            return $nextExecuteAt;
        }

        /** Add hour from user provided */
        $hourInterval = null;
        if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR, $checkValue)) {
            $hours = $checkValue[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR];
            $hourInterval = new \DateInterval(sprintf('PT%dH', $hours)); // e.g PT2H = period time 2 hours
        }

        /** Add minute from user provided */
        $minuteInterval = null;
        if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES, $checkValue)) {
            $minutes = $checkValue[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES];
            $minuteInterval = new \DateInterval(sprintf('PT%dM', $minutes)); // e.g PT30M = period time 30 minutes
        }

        $nextExecuteAt->add($hourInterval);
        $nextExecuteAt->add($minuteInterval);

        while (date_create() > $nextExecuteAt) {
            $nextExecuteAt->add($hourInterval);
            $nextExecuteAt->add($minuteInterval);
        }

        return $nextExecuteAt;
    }

    /**
     * @param \DateTime $lastExecuted
     * @param $checkAt
     * @return \DateTime
     */
    public function getNextExecutedByCheckAt($lastExecuted, $checkAt)
    {
        if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE, $checkAt)) {
            return $lastExecuted;
        }
        $timeZone = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE];

        if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR, $checkAt)) {
            $hour = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR];
        } else {
            $hour = 1;
        }

        if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES, $checkAt)) {
            $minute = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES];
        } else {
            $minute = 1;
        }

        $nextExecuteAt = date_create('now', new \DateTimeZone($timeZone));
        $nextExecuteAt->setTime($hour, $minute);
        
        /** Reconvert to UTC, our database store only time on UTC*/
        $nextExecuteAt->setTimezone(new \DateTimeZone('UTC'));

        /** Add 24 hours to switch tomorrow */
        $dateInterval = new \DateInterval(sprintf('PT%dH', 24)); // e.g PT2H = period time 24 hours
        while ($nextExecuteAt < $lastExecuted) {
            $nextExecuteAt->add($dateInterval);
        }

        return $nextExecuteAt;
    }
}