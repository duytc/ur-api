<?php

namespace UR\Service\Alert\DataSource;


use UR\Model\Core\DataSourceInterface;

abstract class AbstractDataSourceAlert implements DataSourceAlertInterface
{
    protected $alertCode;
    protected $fileName;
    protected $dataSource;
    protected $alertTimeZone;
    protected $alertHour;
    protected $alertMinutes;

    /**
     * AbstractDataSourceAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     */
    public function __construct($alertCode, $fileName, DataSourceInterface $dataSource)
    {
        $this->alertCode = $alertCode;
        $this->fileName = $fileName;
        $this->dataSource = $dataSource;
    }

    /**
     * @return mixed
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }
}