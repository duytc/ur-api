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

    /**
     * @return mixed
     */
    public function getRequires();

    /**
     * @param mixed $requires
     */
    public function setRequires($requires);

    /**
     * @return mixed
     */
    public function getAlertSetting();

    /**
     * @param mixed $alertSetting
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
