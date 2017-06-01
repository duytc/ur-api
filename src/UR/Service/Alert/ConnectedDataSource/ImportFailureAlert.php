<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

class ImportFailureAlert extends AbstractConnectedDataSourceAlert
{
    /** @var mixed */
    private $content;
    /** @var int */
    private $column;
    /** @var int */
    private $row;

    /**
     * ImportFailureAlert constructor.
     * @param $importId
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSourceName
     * @param DataSetInterface $dataSetName
     * @param DataSetInterface $column
     * @param $row
     * @param $content
     */
    public function __construct($importId, $alertCode, $fileName, DataSourceInterface $dataSourceName, DataSetInterface $dataSetName, $column, $row, $content)
    {
        parent::__construct($importId, $alertCode, $fileName, $dataSourceName, $dataSetName);
        $this->column = $column;
        $this->row = $row;
        $this->content = $content;
    }

    /**
     * @inheritdoc
     */
    public function getDetails()
    {
        $details = [
            self::IMPORT_ID => $this->importId,
            self::DATA_SOURCE_NAME => $this->dataSource->getName(),
            self::DATA_SOURCE_ID => $this->dataSource->getId(),
            self::DATA_SET_NAME => $this->dataSet->getName(),
            self::DATA_SET_ID => $this->dataSet->getId(),
            self::FILE_NAME => $this->fileName
        ];

        switch ($this->getAlertCode()) {
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL:
                $details[self::COLUMN] = $this->column;
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING:
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_NUMBER:
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE:
                $details[self::COLUMN] = $this->column;
                $details[self::CONTENT] = $this->content;
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL:
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND:
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND:
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND:
                break;
        }

        return $details;
    }
}