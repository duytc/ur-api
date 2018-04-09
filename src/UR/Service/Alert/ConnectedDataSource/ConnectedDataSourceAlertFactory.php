<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Import\ImportDataException;

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
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY:
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
        if (!in_array(DataAddedAlert::TYPE_DATA_ADDED, $jsonAlerts)) {
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
        if (!in_array(ImportFailureAlert::TYPE_IMPORT_FAILURE, $jsonAlerts)) {
            return null;
        }

        return new ImportFailureAlert($importId, $alertCode, $fileName, $dataSourceName, $dataSetName, $column, $row, $content);
    }

    /**
     * @param $importHistory
     * @param $ex
     * @return null|DataAddedAlert|ImportFailureAlert
     */
    public function getAlertByException($importHistory, $ex)
    {
        if (!$importHistory instanceof ImportHistoryInterface) {
            return null;
        }

        $connectedDataSource = $importHistory->getConnectedDataSource();
        $dataSourceEntry = $importHistory->getDataSourceEntry();

        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();

        /** Import success alert */
        if (!$ex instanceof \Exception) {
            return $connectedDataSourceAlertFactory->getAlert(
                $importHistory->getId(),
                $connectedDataSource->getAlertSetting(),
                AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );
        }

        /** Import fail alert*/
        if ($ex instanceof ImportDataException) {
            return $connectedDataSourceAlertFactory->getAlert(
                $importHistory->getId(),
                $connectedDataSource->getAlertSetting(),
                $ex->getAlertCode(),
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                $ex->getColumn(),
                $ex->getRow(),
                $ex->getContent()
            );
        }

        /** Import fail alert with unexpected error*/
        if ($ex instanceof \Exception) {
            return $alert = $connectedDataSourceAlertFactory->getAlert(
                $importHistory->getId(),
                $connectedDataSource->getAlertSetting(),
                AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_UN_EXPECTED_ERROR,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );
        }

        return null;
    }
}