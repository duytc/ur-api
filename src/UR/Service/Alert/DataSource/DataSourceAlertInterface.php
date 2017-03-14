<?php

namespace UR\Service\Alert\DataSource;

interface DataSourceAlertInterface
{
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD = 100;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL = 101;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API = 102;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT = 103;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT = 104;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT = 105;
    const ALERT_CODE_NO_DATA_RECEIVED_DAILY = 300;
    const ALERT_WRONG_FORMAT_KEY = 'wrongFormat';
    const ALERT_DATA_RECEIVED_KEY = 'dataReceived';
    const ALERT_DATA_NO_RECEIVED_KEY = 'notReceived';
    const ALERT_TYPE_KEY = 'type';
    const ALERT_TIME_ZONE_KEY = 'alertTimeZone';
    const ALERT_HOUR_KEY = 'alertHour';
    const ALERT_MINUTE_KEY = 'alertMinutes';
    const ALERT_ACTIVE_KEY = 'active';
    const ALERT_DETAIL_KEY = 'detail';
    const MESSAGE = 'message';
    const DETAILS = 'detail';
    const DATA_SOURCE_ID = 'dataSourceId';

    public function getAlertMessage();

    public function getAlertDetails();
}