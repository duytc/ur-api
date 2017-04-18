<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ConnectedDataSourceInterface extends ModelInterface
{
    /**
     * @param $id
     * @return mixed
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return self
     */
    public function setName($name);

    /**
     * @return mixed
     */
    public function getMapFields();

    /**
     * @param mixed $mapFields
     * @return self
     */
    public function setMapFields($mapFields);

    /**
     * @return mixed
     */
    public function getFilters();

    /**
     * @param mixed $filters
     * @return self
     */
    public function setFilters($filters);

    /**
     * @return mixed
     */
    public function getTransforms();

    /**
     * @param mixed $transforms
     * @return self
     */
    public function setTransforms($transforms);

    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     * @return self
     */
    public function setDataSource($dataSource);

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet($dataSet);

    /**
     * @return mixed
     */
    public function getRequires();

    /**
     * @param mixed $requires
     * @return self
     */
    public function setRequires($requires);

    /**
     * @return mixed
     */
    public function getAlertSetting();

    /**
     * @param mixed $alertSetting
     * @return self
     */
    public function setAlertSetting($alertSetting);

    /**
     * @return array
     */
    public function getTemporaryFields();

    /**
     * @param array $temporaryFields
     * @return self
     */
    public function setTemporaryFields($temporaryFields);

    /**
     * @return boolean
     */
    public function isReplayData();

    /**
     * @param boolean $replayData
     * @return self
     */
    public function setReplayData($replayData);

    /**
     * @return LinkedMapDataSetInterface[]
     */
    public function getLinkedMapDataSets();

    /**
     * @param LinkedMapDataSetInterface[] $linkedMapDataSets
     * @return self
     */
    public function setLinkedMapDataSets(array $linkedMapDataSets);
}
