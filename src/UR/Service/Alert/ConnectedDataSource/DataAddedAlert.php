<?php

namespace UR\Service\Alert\ConnectedDataSource;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

class DataAddedAlert extends AbstractConnectedDataSourceAlert
{
    /**
     * DataAddedAlert constructor.
     * @param $importId
     * @param $alertCode
     * @param $fileName
     * @param DataSourceInterface $dataSource
     * @param DataSetInterface $dataSet
     */
    public function __construct($importId, $alertCode, $fileName, DataSourceInterface $dataSource, DataSetInterface $dataSet)
    {
        parent::__construct($importId, $alertCode, $fileName, $dataSource, $dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getDetails()
    {
        return [
            self::KEY_IMPORT_ID => $this->importId,
            self::DATA_SOURCE_NAME => $this->dataSource->getName(),
            self::DATA_SOURCE_ID => $this->dataSource->getId(),
            self::KEY_DATA_SET_NAME => $this->dataSet->getName(),
            self::KEY_DATA_SET_ID => $this->dataSet->getId(),
            self::FILE_NAME => $this->fileName
        ];
    }
}