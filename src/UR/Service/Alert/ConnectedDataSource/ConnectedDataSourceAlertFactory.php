<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

class ConnectedDataSourceAlertFactory
{
    /**
     * @param $importId
     * @param $jsonAlerts
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     * @param DataSetInterface $dataSet
     * @param null $column
     * @param $row
     * @param $content
     * @return null|DataAddedAlert|ImportFailureAlert
     */
    public function getAlert($importId, $jsonAlerts, $alertCode, $fileName, DataSourceInterface $dataSource, DataSetInterface $dataSet, $column, $row, $content)
    {
        $alertObject = null;
        if (!is_array($jsonAlerts)) {
            return null;
        }

        switch ($alertCode) {
            case DataAddedAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY:
                return $this->getDataLoadedSuccessfulAlert($importId, $alertCode, $jsonAlerts, $fileName, $dataSource, $dataSet);
            default:
                return $this->getDataLoadedFailureAlert($importId, $alertCode, $jsonAlerts, $fileName, $dataSource, $dataSet, $column, $row, $content);
        }
    }

    /**
     * @param $importId
     * @param $alertCode
     * @param $jsonAlerts
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @return null|DataAddedAlert
     */
    private function getDataLoadedSuccessfulAlert($importId, $alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName)
    {
        if (!in_array(DataAddedAlert::DATA_ADDED, $jsonAlerts)) {
            return null;
        }

        return new DataAddedAlert($importId, $alertCode, $fileName, $dataSourceName, $dataSetName);
    }

    /**
     * @param $importId
     * @param $alertCode
     * @param $jsonAlerts
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     * @param $column
     * @param $row
     * @param $content
     * @return null|ImportFailureAlert
     */
    private function getDataLoadedFailureAlert($importId, $alertCode, $jsonAlerts, $fileName, $dataSourceName, $dataSetName, $column, $row, $content)
    {
        if (!in_array(ImportFailureAlert::IMPORT_FAILURE, $jsonAlerts)) {
            return null;
        }

        return new ImportFailureAlert($importId, $alertCode, $fileName, $dataSourceName, $dataSetName, $column, $row, $content);
    }
}