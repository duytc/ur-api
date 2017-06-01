<?php

namespace UR\Service\Alert\DataSource;

use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\AlertDTOInterface;

interface DataSourceAlertInterface extends AlertDTOInterface
{
    const ALERT_WRONG_FORMAT_KEY = 'wrongFormat';
    const ALERT_DATA_RECEIVED_KEY = 'dataReceived';
    const ALERT_DATA_NO_RECEIVED_KEY = 'notReceived';
    const ALERT_TYPE_KEY = 'type';
    const ALERT_TIME_ZONE_KEY = 'alertTimeZone';
    const ALERT_HOUR_KEY = 'alertHour';
    const ALERT_MINUTE_KEY = 'alertMinutes';
    const ALERT_ACTIVE_KEY = 'active';
    const ALERT_DETAIL_KEY = 'detail';
    const DATE = 'date';

    /**
     * @return mixed
     */
    public function getDetails();

    /**
     * @return mixed
     */
    public function getAlertCode();

    /**
     * @return mixed
     */
    public function getFileName();

    /**
     * @return null|DataSourceInterface
     */
    public function getDataSource();

    /**
     * @return null|int
     */
    public function getDataSourceId();
}