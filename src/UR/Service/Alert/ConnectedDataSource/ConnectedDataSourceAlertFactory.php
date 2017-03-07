<?php

namespace UR\Service\Alert\ConnectedDataSource;


class ConnectedDataSourceAlertFactory
{
    /**
     * @param $jsonAlerts
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @param null $column
     * @return DataAddedAlert|ImportFailureAlert|null
     */
    public function getAlert($jsonAlerts, $alertCode, $fileName, $dataSourceName, $dataSetName, $column)
    {
        $alertObject = null;
        if (!is_array($jsonAlerts)) {
            return null;
        }

        switch ($alertCode) {
            case DataAddedAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY:
                return $this->getDataLoadedSuccessfulAlert($alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName);
            default:
                return $this->getDataLoadedFailureAlert($alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName, $column);
        }
    }

    /**
     * @param $alertCode
     * @param $jsonAlerts
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @return DataAddedAlert|null
     */
    private function getDataLoadedSuccessfulAlert($alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName)
    {
        if (!in_array(DataAddedAlert::DATA_ADDED, $jsonAlerts)) {
            return null;
        }

        return new DataAddedAlert($alertCode, $fileName, $dataSourceName, $dataSetName);
    }

    /**
     * @param $alertCode
     * @param $jsonAlerts
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @param $column
     * @return ImportFailureAlert|null
     */
    private function getDataLoadedFailureAlert($alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName, $column)
    {
        if (!in_array(ImportFailureAlert::IMPORT_FAILURE, $jsonAlerts)) {
            return null;
        }

        return new ImportFailureAlert($alertCode, $fileName, $dataSourceName, $dataSetName, $column);
    }
}