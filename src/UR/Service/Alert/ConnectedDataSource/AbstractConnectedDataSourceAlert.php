<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

abstract class AbstractConnectedDataSourceAlert implements ConnectedDataSourceAlertInterface
{
    protected $alertCode;
    protected $fileName;
    protected $dataSource;
    protected $dataSet;
    protected $importId;

    /**
     * AbstractConnectedDataSourceAlert constructor.
     * @param $importId
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     * @param DataSetInterface $dataSet
     */
    public function __construct($importId, $alertCode, $fileName, DataSourceInterface $dataSource, DataSetInterface $dataSet)
    {
        $this->importId = $importId;
        $this->alertCode = $alertCode;
        $this->fileName = $fileName;
        $this->dataSource = $dataSource;
        $this->dataSet = $dataSet;
    }

    public abstract function getDetails();

    /**
     * @return mixed
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }
}