<?php

namespace UR\Service\Alert\ConnectedDataSource;

class DataAddedAlert extends AbstractConnectedDataSourceAlert
{
    /**
     * DataAddedAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     */
    public function __construct($alertCode, $fileName, $dataSourceName, $dataSetName)
    {
        parent::__construct($alertCode, $fileName, $dataSourceName, $dataSetName);
    }

    public function getMessage()
    {
        return sprintf('File "%s" from data source "%s" has been successfully imported to data set "%s".', $this->fileName, $this->dataSourceName, $this->dataSetName);
    }

    public function getDetails()
    {
        return $this->getMessage();
    }
}