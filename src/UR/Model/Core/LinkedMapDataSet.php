<?php

namespace UR\Model\Core;

class LinkedMapDataSet implements LinkedMapDataSetInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ConnectedDataSourceInterface
     */
    protected $connectedDataSource;

    /**
     * @var DataSetInterface
     */
    protected $mapDataSet;

    /**
     * @var array
     */
    protected $mappedFields;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return ConnectedDataSourceInterface
     */
    public function getConnectedDataSource()
    {
        return $this->connectedDataSource;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return self
     */
    public function setConnectedDataSource($connectedDataSource)
    {
        $this->connectedDataSource = $connectedDataSource;
        return $this;
    }

    /**
     * @return DataSetInterface
     */
    public function getMapDataSet()
    {
        return $this->mapDataSet;
    }

    /**
     * @param DataSetInterface $mapDataSet
     * @return self
     */
    public function setMapDataSet($mapDataSet)
    {
        $this->mapDataSet = $mapDataSet;
        return $this;
    }

    /**
     * @return array
     */
    public function getMappedFields()
    {
        return $this->mappedFields;
    }

    /**
     * @param array $mappedFields
     * @return self
     */
    public function setMappedFields($mappedFields)
    {
        $this->mappedFields = $mappedFields;
        return $this;
    }
}