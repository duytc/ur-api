<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface LinkedMapDataSetInterface extends ModelInterface
{
    /**
     * @param int $id
     * @return self
     */
    public function setId($id);

    /**
     * @return ConnectedDataSourceInterface
     */
    public function getConnectedDataSource();

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return self
     */
    public function setConnectedDataSource($connectedDataSource);

    /**
     * @return DataSetInterface
     */
    public function getMapDataSet();

    /**
     * @param DataSetInterface $mapDataSet
     * @return self
     */
    public function setMapDataSet($mapDataSet);

    /**
     * @return array
     */
    public function getMappedFields();

    /**
     * @param array $mappedFields
     * @return self
     */
    public function setMappedFields($mappedFields);
}