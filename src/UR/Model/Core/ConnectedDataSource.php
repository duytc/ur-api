<?php

namespace UR\Model\Core;

class ConnectedDataSource implements ConnectedDataSourceInterface
{
    protected $id;
    protected $mapFields;
    protected $filters;
    protected $transforms;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

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
    public function getMapFields()
    {
        return $this->mapFields;
    }

    /**
     * @inheritdoc
     */
    public function setMapFields($mapFields)
    {
        $this->mapFields = $mapFields;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @inheritdoc
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
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
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
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
}