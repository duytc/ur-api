<?php

namespace UR\Service\Alert\DataSource;


use UR\Model\Core\DataSourceInterface;

abstract class AbstractDataSourceAlert implements DataSourceAlertInterface
{
    /** @var String */
    protected $alertCode;
    /** @var String */
    protected $fileName;
    /** @var DataSourceInterface */
    protected $dataSource;

    public static $SUPPORTED_ALERT_SETTING_KEYS = [
        self::ALERT_TYPE_VALUE_WRONG_FORMAT,
        self::ALERT_TYPE_VALUE_DATA_RECEIVED,
        self::ALERT_TYPE_VALUE_DATA_NO_RECEIVED,
    ];

    /**
     * AbstractDataSourceAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     */
    public function __construct($alertCode, $fileName, DataSourceInterface $dataSource)
    {
        $this->alertCode = $alertCode;
        $this->fileName = $fileName;
        $this->dataSource = $dataSource;
    }

    /**
     * @inheritdoc
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }

    /**
     * @inheritdoc
     */
    public function getFileName()
    {
        return $this->fileName;
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
    public function getDataSourceId()
    {
        return ($this->dataSource instanceof DataSourceInterface) ? $this->dataSource->getId() : null;
    }
}