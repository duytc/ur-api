<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

abstract class AbstractConnectedDataSourceAlert implements ConnectedDataSourceAlertInterface
{
    /** @var int */
    protected $alertCode;
    /** @var string */
    protected $fileName;
    /** @var DataSourceInterface */
    protected $dataSource;
    /** @var DataSetInterface */
    protected $dataSet;
    /** @var int */
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

    /**
     * @inheritdoc
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }

    /**
     * @inheritdoc
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @inheritdoc
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceId()
    {
        return ($this->dataSource instanceof DataSourceInterface) ? $this->dataSource->getId() : null;
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @inheritdoc
     */
    public function getImportId()
    {
        return $this->importId;
    }
}