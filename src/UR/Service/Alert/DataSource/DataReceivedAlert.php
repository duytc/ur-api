<?php

namespace UR\Service\Alert\DataSource;


use UR\Model\Core\DataSourceInterface;

class DataReceivedAlert extends AbstractDataSourceAlert
{
    /**
     * DataReceivedAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     */
    public function __construct($alertCode, $fileName, DataSourceInterface $dataSource)
    {
        parent::__construct($alertCode, $fileName, $dataSource);
    }

    public function getDetails()
    {
        return [
            self::DATA_SOURCE_ID => $this->dataSource->getId(),
            self::DATA_SOURCE_NAME => $this->dataSource->getName(),
            self::FILE_NAME => $this->fileName
        ];
    }
}