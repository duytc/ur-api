<?php

namespace UR\Model\Core;


use UR\Model\User\UserEntityInterface;

class Alert implements AlertInterface
{
    public static $SUPPORTED_ALERT_CODES = [
        /* Alert for data source */
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD,
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL,
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API,
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT,
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT,
        self::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT,
        self::ALERT_CODE_DATA_SOURCE_NO_DATA_RECEIVED_DAILY,

        /* Alert for connected data source */
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_NUMBER,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_NO_FILE_PREVIEW,
        self::ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR,

        self::ALERT_CODE_BROWSER_AUTOMATION_LOGIN_FAIL,
        self::ALERT_CODE_BROWSER_AUTOMATION_TIME_OUT,
        self::ALERT_CODE_BROWSER_AUTOMATION_PASSWORD_EXPIRY,

        self::ALERT_CODE_EMAIL_WEB_HOOK_INVALID_EMAIL_SETTING,

        AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_PENDING,
        AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_SUCCESS
    ];

    public static $ALERT_CODE_TO_TYPE_MAP = [
        /* Alert for data source */
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD =>Alert::ALERT_TYPE_INFO,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL =>Alert::ALERT_TYPE_INFO,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API => Alert::ALERT_TYPE_INFO,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_DATA_SOURCE_NO_DATA_RECEIVED_DAILY => Alert::ALERT_TYPE_WARNING,

        /* Alert for connected data source */
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY => Alert::ALERT_TYPE_INFO,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_NUMBER => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND =>  Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_NO_FILE_PREVIEW => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR => Alert::ALERT_TYPE_ERROR,

        AlertInterface::ALERT_CODE_BROWSER_AUTOMATION_LOGIN_FAIL => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_BROWSER_AUTOMATION_TIME_OUT => Alert::ALERT_TYPE_ERROR,
        AlertInterface::ALERT_CODE_BROWSER_AUTOMATION_PASSWORD_EXPIRY => Alert::ALERT_TYPE_WARNING,

        AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_PENDING => Alert::ALERT_TYPE_INFO,
        AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_SUCCESS => Alert::ALERT_TYPE_INFO
    ];

    protected $id;
    protected $code;
    protected $isRead;
    protected $detail;
    protected $createdDate;
    protected $type;
    /** @var boolean */
    protected $isSent;

    /**
     * @var UserEntityInterface
     */
    protected $publisher;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /** @var OptimizationIntegrationInterface */
    protected $optimizationIntegration;

    public function __construct()
    {
        $this->isRead = false;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsRead()
    {
        return $this->isRead;
    }

    /**
     * @inheritdoc
     */
    public function setIsRead($isRead)
    {
        $this->isRead = $isRead;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
        
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * @inheritdoc
     */
    public function setDetail($detail)
    {
        $this->detail = $detail;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @inheritdoc
     */
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsSent()
    {
        return $this->isSent;
    }

    /**
     * @inheritdoc
     */
    public function setIsSent($isSent)
    {
        $this->isSent = $isSent;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationIntegration()
    {
        return $this->optimizationIntegration;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizationIntegration($optimizationIntegration)
    {
        $this->optimizationIntegration = $optimizationIntegration;

        return $this;
    }
}