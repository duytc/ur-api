<?php

namespace UR\Service\Alert\DataSource;


abstract class AbstractDataSourceAlert implements DataSourceAlertInterface
{
    protected $alertCode;
    protected $fileName;
    protected $dataSourceName;
    protected $alertTimeZone;
    protected $alertHour;
    protected $alertMinutes;

    /**
     * AbstractDataSourceAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     * @param $alertTimeZone
     * @param $alertHour
     * @param $alertMinutes
     */
    public function __construct($alertCode, $fileName, $dataSourceName, $alertTimeZone, $alertHour, $alertMinutes)
    {
        $this->alertCode = $alertCode;
        $this->fileName = $fileName;
        $this->dataSourceName = $dataSourceName;
        $this->alertTimeZone = $alertTimeZone;
        $this->alertHour = $alertHour;
        $this->alertMinutes = $alertMinutes;
    }

    public function getAlertMessage()
    {
        return [self::MESSAGE => $this->getMessage()];
    }

    public function getAlertDetails()
    {
        return $this->getDetails();
    }

    abstract protected function getMessage();

    abstract protected function getDetails();

    /**
     * @return mixed
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }
}