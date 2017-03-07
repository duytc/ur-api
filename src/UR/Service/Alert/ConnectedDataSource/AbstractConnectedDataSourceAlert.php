<?php

namespace UR\Service\Alert\ConnectedDataSource;


abstract class AbstractConnectedDataSourceAlert implements ConnectedDataSourceAlertInterface
{
    protected $alertCode;
    protected $fileName;
    protected $dataSourceName;
    protected $dataSetName;

    /**
     * AbstractConnectedDataSourceAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $dataSetName
     */
    public function __construct($alertCode, $fileName, $dataSourceName, $dataSetName)
    {
        $this->alertCode = $alertCode;
        $this->fileName = $fileName;
        $this->dataSourceName = $dataSourceName;
        $this->dataSetName = $dataSetName;
    }


    public function getAlertMessage()
    {
        return $this->getMessage();
    }

    public function getAlertDetails()
    {
        return $this->getDetails();
    }

    public abstract function getMessage();

    public abstract function getDetails();

    /**
     * @return mixed
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }
}