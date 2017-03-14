<?php

namespace UR\Service\Alert\DataSource;


class DataReceivedAlert extends AbstractDataSourceAlert
{
    /**
     * DataReceivedAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $alertTimeZone
     * @param $alertHour
     * @param $alertMinutes
     */
    public function __construct($alertCode, $fileName, $dataSourceName, $alertTimeZone, $alertHour, $alertMinutes)
    {
        parent::__construct($alertCode, $fileName, $dataSourceName, $alertTimeZone, $alertHour, $alertMinutes);
    }

    /**
     * @return string
     */
    protected function getMessage()
    {
        switch ($this->alertCode) {
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                $message = sprintf('File "%s" has been successfully uploaded to data source "%s".', $this->fileName, $this->dataSourceName);
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL:
                $message = sprintf('File "%s" has been successfully imported to data source "%s" from email.', $this->fileName, $this->dataSourceName);
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API:
                $message = sprintf('File "%s" has been successfully received to data source "%s" from api.', $this->fileName, $this->dataSourceName);
                break;
            default:
                return "";
        }

        return $message;
    }

    protected function getDetails()
    {
        return [self::DETAILS => $this->getMessage()];
    }
}