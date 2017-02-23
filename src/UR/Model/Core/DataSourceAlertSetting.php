<?php

namespace UR\Model\Core;


class DataSourceAlertSetting
{
    private $type;
    private $alertTimeZone;
    private $alertHour;
    private $alertMinutes;
    private $active;

    /**
     * DataSourceAlertSetting constructor.
     * @param $type
     * @param $alertTimeZone
     * @param $alertHour
     * @param $alertMinutes
     * @param $active
     */
    public function __construct($type, $alertTimeZone, $alertHour, $alertMinutes, $active)
    {
        $this->type = $type;
        $this->alertTimeZone = $alertTimeZone;
        $this->alertHour = $alertHour;
        $this->alertMinutes = $alertMinutes;
        $this->active = $active;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getAlertTimeZone()
    {
        return $this->alertTimeZone;
    }

    /**
     * @param mixed $alertTimeZone
     */
    public function setAlertTimeZone($alertTimeZone)
    {
        $this->alertTimeZone = $alertTimeZone;
    }

    /**
     * @return mixed
     */
    public function getAlertHour()
    {
        return $this->alertHour;
    }

    /**
     * @param mixed $alertHour
     */
    public function setAlertHour($alertHour)
    {
        $this->alertHour = $alertHour;
    }

    /**
     * @return mixed
     */
    public function getAlertMinutes()
    {
        return $this->alertMinutes;
    }

    /**
     * @param mixed $alertMinutes
     */
    public function setAlertMinutes($alertMinutes)
    {
        $this->alertMinutes = $alertMinutes;
    }

    /**
     * @return mixed
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param mixed $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }
}