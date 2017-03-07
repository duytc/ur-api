<?php

namespace UR\Service\Alert\DataSource;


class DataSourceAlertFactory
{
    const ALERT_UPLOADED_SUCCESSFUL = "uploaded_successful";

    /**
     * @param $jsonAlerts
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @return null|DataReceivedAlert|WrongFormatAlert
     */
    public function getAlert($jsonAlerts, $alertCode, $fileName, $dataSourceName)
    {
        $alertObject = null;
        if (!is_array($jsonAlerts)) {
            return null;
        }

        foreach ($jsonAlerts as $jsonAlert) {
            if (!is_array($jsonAlert)
                || !array_key_exists(DataSourceAlertInterface::ALERT_TYPE_KEY, $jsonAlert)
                || !array_key_exists(DataSourceAlertInterface::ALERT_TIME_ZONE_KEY, $jsonAlert)
                || !array_key_exists(DataSourceAlertInterface::ALERT_HOUR_KEY, $jsonAlert)
                || !array_key_exists(DataSourceAlertInterface::ALERT_MINUTE_KEY, $jsonAlert)
                || !array_key_exists(DataSourceAlertInterface::ALERT_ACTIVE_KEY, $jsonAlert)
            ) {
                continue;
            }

            if (!$jsonAlert[DataSourceAlertInterface::ALERT_ACTIVE_KEY]) {
                continue;
            }

            switch ($alertCode) {
                case DataReceivedAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                    $alertObject = $this->getDataReceivedAlert($jsonAlert, $alertCode, $fileName, $dataSourceName);
                    break;

                case WrongFormatAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                case WrongFormatAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
                case WrongFormatAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                    $alertObject = $this->getWrongFormatAlert($jsonAlert, $alertCode, $fileName, $dataSourceName);
                    break;
            }
        }

        return $alertObject;
    }

    /**
     * @param array $jsonAlert
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @return null|DataReceivedAlert
     */
    private function getDataReceivedAlert(array $jsonAlert, $alertCode, $fileName, $dataSourceName)
    {
        if (strcmp($jsonAlert[DataSourceAlertInterface::ALERT_TYPE_KEY], DataSourceAlertInterface::ALERT_DATA_RECEIVED_KEY) !== 0) {
            return null;
        }

        return new DataReceivedAlert(
            $alertCode,
            $fileName,
            $dataSourceName,
            $jsonAlert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY],
            $jsonAlert[DataSourceAlertInterface::ALERT_HOUR_KEY],
            $jsonAlert[DataSourceAlertInterface::ALERT_MINUTE_KEY]
        );
    }

    /**
     * @param $jsonAlert
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @return null|WrongFormatAlert
     */
    private function getWrongFormatAlert($jsonAlert, $alertCode, $fileName, $dataSourceName)
    {
        if (strcmp($jsonAlert[DataSourceAlertInterface::ALERT_TYPE_KEY], DataSourceAlertInterface::ALERT_WRONG_FORMAT_KEY) !== 0) {
            return null;
        }

        return new WrongFormatAlert(
            $alertCode,
            $fileName,
            $dataSourceName,
            $jsonAlert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY],
            $jsonAlert[DataSourceAlertInterface::ALERT_HOUR_KEY],
            $jsonAlert[DataSourceAlertInterface::ALERT_MINUTE_KEY]
        );
    }
}