<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ConnectedDataSourceInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return mixed
     */
    public function getMapFields();

    /**
     * @param mixed $mapFields
     */
    public function setMapFields($mapFields);

    /**
     * @return mixed
     */
    public function getFilters();

    /**
     * @param mixed $filters
     */
    public function setFilters($filters);

    /**
     * @return mixed
     */
    public function getTransforms();

    /**
     * @param mixed $transforms
     */
    public function setTransforms($transforms);

    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     */
    public function setDataSource($dataSource);

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     */
    public function setDataSet($dataSet);
}