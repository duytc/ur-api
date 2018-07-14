<?php

namespace UR\Service\Alert\DataSet;


use UR\Model\Core\DataSetInterface;

abstract class AbstractDataSetAlert implements DataSetAlertInterface
{
    /** @var String */
    protected $alertType;
    /** @var String */
    protected $alertCode;
    /** @var DataSetInterface */
    protected $dataSet;

    public static $SUPPORTED_ALERT_SETTING_KEYS = [
        self::ALERT_TYPE_VALUE_DATA_AUGMENTED_DATA_SET_CHANGED,
    ];

    /**
     * AbstractDataSetAlert constructor.
     * @param $alertType
     * @param $alertCode
     * @param $fileName
     * @param DataSetInterface $dataSet
     */
    public function __construct($alertType, $alertCode, DataSetInterface $dataSet)
    {
        $this->alertType = $alertType;
        $this->alertCode = $alertCode;
        $this->dataSet = $dataSet;
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
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @inheritdoc
     */
    public function getDataSetId()
    {
        return ($this->dataSet instanceof DataSetInterface) ? $this->dataSet->getId() : null;
    }
}