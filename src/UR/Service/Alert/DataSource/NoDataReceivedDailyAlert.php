<?php

namespace UR\Service\Alert\DataSource;


use DateInterval;
use DateTime;
use UR\Model\Core\DataSourceInterface;

class NoDataReceivedDailyAlert extends AbstractDataSourceAlert
{
    /**
     * @var DataSourceInterface $dataSource
     */
    protected $dataSource;

    /**
     * NoDataReceivedDailyAlert constructor.
     * @param $alertCode
     * @param $dataSource
     * @param $alertTimeZone
     * @param $alertHour
     * @param $alertMinutes
     */
    public function __construct($alertCode, DataSourceInterface $dataSource, $alertTimeZone, $alertHour, $alertMinutes)
    {
        parent::__construct($alertCode, null, $dataSource->getName(), $alertTimeZone, $alertHour, $alertMinutes);
        $this->dataSource = $dataSource;
    }

    protected function getMessage()
    {
        return sprintf('Data source "%s" has not received data today (%s)', $this->dataSourceName, sprintf(date("Y-m-d")));
    }

    protected function getDetails()
    {
        return [self::DATA_SOURCE_ID => $this->dataSource->getId(), self::DETAILS => $this->getMessage()];
    }

    /**
     * @return mixed
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    public function getNextAlertTime()
    {
        $currentDate = new DateTime($this->alertTimeZone);

        /*
         * setup alert time
         */
        $newTime = new DateTime();
        $newTime->setTime($this->alertHour, $this->alertMinutes, 0);
        $newTime->setTimezone(new \DateTimeZone($this->alertTimeZone));

        if ($currentDate < $newTime) {
            return $newTime;
        }

        while ($currentDate >= $newTime) {
            $newTime->add(new DateInterval('P1D'));
        }

        return $newTime;
    }

    /**
     * @return mixed
     */
    public function getAlertHour()
    {
        return $this->alertHour;
    }

    /**
     * @return mixed
     */
    public function getAlertMinutes()
    {
        return $this->alertMinutes;
    }

    /**
     * @return mixed
     */
    public function getAlertTimeZone()
    {
        return $this->alertTimeZone;
    }
}