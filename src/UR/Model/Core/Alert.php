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
        self::ALERT_CODE_BROWSER_AUTOMATION_TIME_OUT
    ];

    protected $id;
    protected $code;
    protected $isRead;
    protected $detail;
    protected $createdDate;

    /**
     * @var UserEntityInterface
     */
    protected $publisher;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

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
}