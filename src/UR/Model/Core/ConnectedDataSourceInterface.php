<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ConnectedDataSourceInterface extends ModelInterface
{
    const LINKED_TYPE_AUGMENTATION = 'augmentation';
    const PREFIX_TEMP_FIELD = '__$$TEMP$$';
    const PREFIX_FILE_FIELD = '__$$FILE$$';

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

    /**
     * @return mixed
     */
    public function getLastActivity();

    /**
     * @param mixed $lastActivity
     * @return self
     */
    public function setLastActivity($lastActivity);

    /**
     * @return mixed
     */
    public function getLinkedType();

    /**
     * @param mixed $_linkedType
     * @return self
     */
    public function setLinkedType($_linkedType);

    /**
     * @return int
     */
    public function getTotalRow();

    /**
     * @param int $totalRow
     * @return self
     */
    public function setTotalRow(int $totalRow);

    /**
     * @return int
     */
    public function getNumChanges();

    /**
     * @param int $numChanges
     * @return self
     */
    public function setNumChanges($numChanges);

    /**
     * @return self
     */
    public function increaseNumChanges();
}
