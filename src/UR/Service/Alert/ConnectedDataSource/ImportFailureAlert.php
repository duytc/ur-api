<?php

namespace UR\Service\Alert\ConnectedDataSource;


class ImportFailureAlert extends AbstractConnectedDataSourceAlert
{
    private $column;

    /**
     * ImportFailureAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @param $column
     */
    public function __construct($alertCode, $fileName, $dataSourceName, $dataSetName, $column)
    {
        parent::__construct($alertCode, $fileName, $dataSourceName, $dataSetName);
        $this->column = $column;
    }

    public function getMessage()
    {
        $message = "";
        $importErrorMessage = sprintf('Failed to import file %s from data source  "%s" to data set "%s".', $this->fileName, $this->dataSourceName, $this->dataSetName);
        switch ($this->getAlertCode()) {
            case self::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf('%s - mapping error: no Field in file is mapped to data set.', $importErrorMessage);
                break;

            case self::ALERT_CODE_WRONG_TYPE_MAPPING:
                $message = sprintf('%s - mapping error: invalid type on field "%s".', $importErrorMessage, $this->column);
                break;

            case self::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf('%s - field "%s" is required but not found in file.', $importErrorMessage, $this->column);
                break;

            case self::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf('%s - invalid number format on field "%s".', $importErrorMessage, $this->column);
                break;
            case self::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf('%s - invalid date format on field "%s".', $importErrorMessage, $this->column);
                break;

            case self::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf('%s - no header found.', $importErrorMessage);
                break;

            case self::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf('%s - no data found.', $importErrorMessage);
                break;

            case self::ALERT_CODE_FILE_NOT_FOUND:
                $message = sprintf('%s - error: %s.', $importErrorMessage, ' file does not exist ');
                break;
        }

        return $message;
    }

    public function getDetails()
    {
        return $this->getMessage();
    }
}