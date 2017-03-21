<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

class ImportFailureAlert extends AbstractConnectedDataSourceAlert
{
    private $column;
    private $row;

    /**
     * ImportFailureAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @param $column
     * @param $row
     */
    public function __construct($alertCode, $fileName, DataSourceInterface $dataSourceName, DataSetInterface $dataSetName, $column, $row)
    {
        parent::__construct(null, $alertCode, $fileName, $dataSourceName, $dataSetName);
        $this->column = $column;
        $this->row = $row;
    }

    public function getDetails()
    {
        $details = [
            self::DATA_SOURCE_NAME => $this->dataSource->getName(),
            self::DATA_SOURCE_ID => $this->dataSource->getId(),
            self::DATA_SET_NAME => $this->dataSet->getName(),
            self::DATA_SET_ID => $this->dataSet->getId(),
            self::FILE_NAME => $this->fileName
        ];

        switch ($this->getAlertCode()) {
            case self::ALERT_CODE_WRONG_TYPE_MAPPING:
            case self::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL:
            case self::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER:
            case self::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE:
                $details[self::COLUMN] = $this->column;
                break;
            case self::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL:
            case self::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND:
            case self::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND:
            case self::ALERT_CODE_FILE_NOT_FOUND:
                break;
        }

        return $details;
    }
}