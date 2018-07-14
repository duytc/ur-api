<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface AlertInterface extends ModelInterface
{
    /* define all alert codes */
    // TODO: move all other alert codes to here...

    /* Alert for data set */
    const ALERT_CODE_DATA_AUGMENTED_DATA_SET_CHANGED = 1001;

    /* Alert for data source */
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD = 1100;
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL = 1101;
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API = 1102;
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT = 1103;
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT = 1104;
    const ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT = 1105;
    const ALERT_CODE_DATA_SOURCE_NO_DATA_RECEIVED_DAILY = 1300;

    /* Alert for connected data source */
    const ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY = 1200;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL = 1201;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL = 1202;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_NUMBER = 1203;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE = 1204;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND = 1205;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND = 1206;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING = 1207;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND = 1208;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_NO_FILE_PREVIEW = 1209;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE = 1210;
    const ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR = 2000;

    const ALERT_CODE_BROWSER_AUTOMATION_LOGIN_FAIL = 2001;
    const ALERT_CODE_BROWSER_AUTOMATION_TIME_OUT = 2002;
    const ALERT_CODE_BROWSER_AUTOMATION_PASSWORD_EXPIRY = 2003;

    const ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_PENDING = 3000;
    const ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_SUCCESS = 3001;

    /* const type alert */
    const ALERT_TYPE_INFO = 'info';
    const ALERT_TYPE_WARNING = 'warning';
    const ALERT_TYPE_ERROR = 'error';
    const ALERT_TYPE_ACTION_REQUIRED = 'actionRequired';
    const SUPPORT_ALERT_TYPES = [
        self::ALERT_TYPE_INFO,
        self::ALERT_TYPE_WARNING,
        self::ALERT_TYPE_ERROR,
        self::ALERT_TYPE_ACTION_REQUIRED,
    ];
    const ALERT_CODE_EMAIL_WEB_HOOK_INVALID_EMAIL_SETTING = 2004;

    /**
     * @return mixed    
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getCode();

    /**
     * @param mixed $code
     */
    public function setCode($code);

    /**
     * @return mixed
     */
    public function getIsRead();

    /**
     * @param mixed $isRead
     */
    public function setIsRead($isRead);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return PublisherInterface
     */
    public function getPublisher();

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher($publisher);

    /**
     * @return mixed
     */
    public function getDetail();

    /**
     * @param mixed $detail
     * return self
     */
    public function setDetail($detail);

    /**
     * @return null|DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param null|DataSourceInterface $dataSource
     * return self
     */
    public function setDataSource($dataSource);

    /**
     * @return null|DataSetInterface
     */
    public function getDataSet();

    /**
     * @param null|DataSetInterface $dataSet
     * return self
     */
    public function setDataSet($dataSet);

    /**
     * @return mixed
     */
    public function getType();

    /**
     * @param mixed $type
     * return self
     */
    public function setType($type);

    /**
     * @return boolean
     */
    public function getIsSent();

    /**
     * @param boolean $isSent
     * @return self
     */
    public function setIsSent($isSent);

    /**
     * @return OptimizationIntegrationInterface
     */
    public function getOptimizationIntegration();

    /**
     * @param OptimizationIntegrationInterface $optimizationRule
     * @return self
     */
    public function setOptimizationIntegration($optimizationRule);

    /**
     * @return mixed
     */
    public function getIsSentReminder();

    /**
     * @param mixed $isSentReminder
     */
    public function setIsSentReminder($isSentReminder);
}