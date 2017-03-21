<?php

namespace UR\Service\Alert\DataSource;


class WrongFormatAlert extends AbstractDataSourceAlert
{
    /**
     * WrongFormatAlert constructor.
     * @param $alertCode
     * @param $fileName
     * @param $dataSourceName
     */
    public function __construct($alertCode, $fileName, $dataSourceName)
    {
        parent::__construct($alertCode, $fileName, $dataSourceName);
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