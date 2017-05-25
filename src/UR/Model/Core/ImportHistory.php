<?php

namespace UR\Model\Core;

class ImportHistory implements ImportHistoryInterface
{
    protected $id;
    protected $createdDate;
    protected $description;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var ConnectedDataSourceInterface
     */
    protected $connectedDataSource;

    /**
     * @var DataSourceEntryInterface
     */
    protected $dataSourceEntry;

    public function __construct()
    {

    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntry()
    {
        return $this->dataSourceEntry;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceEntry($dataSourceEntry)
    {
        $this->dataSourceEntry = $dataSourceEntry;
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSource()
    {
        return $this->connectedDataSource;
    }

    /**
     * @inheritdoc
     */
    public function setConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        $this->connectedDataSource = $connectedDataSource;
        return $this;
    }
}