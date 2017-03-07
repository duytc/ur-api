<?php

namespace UR\Service\Alert\DataSource;


class WrongFormatAlert extends AbstractDataSourceAlert
{
    /**
     * WrongFormatAlert constructor.
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

    protected function getMessage()
    {
        switch ($this->alertCode) {
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                $message = sprintf('Failed to upload file "%s" to data source "%s" - wrong format file.', $this->fileName, $this->dataSourceName);
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
                $message = sprintf('Failed to receive file "%s" from email to data source "%s" - wrong format error.', $this->fileName, $this->dataSourceName);
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                $message = sprintf('Failed to receive file "%s" from api to data source "%s" - wrong format error.', $this->fileName, $this->dataSourceName);
                break;
            default:
                return "";
        }

        return $message;
    }

    protected function getDetails()
    {
        return $this->getMessage();
    }
}