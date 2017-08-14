<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSetInterface extends ModelInterface
{
    const ID_COLUMN = '__id';
    const DATA_SOURCE_ID_COLUMN = '__data_source_id';
    const CONNECTED_DATA_SOURCE_ID_COLUMN = '__connected_data_source_id';
    const IMPORT_ID_COLUMN = '__import_id';
    const UNIQUE_ID_COLUMN = '__unique_id';
    const OVERWRITE_DATE = '__overwrite_date';
    const ENTRY_DATE_COLUMN = '__entry_date';
    const UNIQUE_HASH_INX = 'unique_hash_idx';
    const MAPPING_IS_LEFT_SIDE = '__is_left_side';
    const MAPPING_IS_ASSOCIATED = '__is_associated';
    const MAPPING_IS_IGNORED = '__is_ignored';
    const ALLOW_OVERWRITE_EXISTING_DATA = 'allowOverwriteExistingData';

    const NAME_COLUMN = 'name';
    const DIMENSIONS_COLUMN = 'dimensions';
    const METRICS_COLUMN = 'metrics';
    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @param mixed $name
     */
    public function setName($name);

    /**
     * @return array
     */
    public function getDimensions();

    /**
     * @param mixed $dimensions
     */
    public function setDimensions($dimensions);

    /**
     * @return array
     */
    public function getMetrics();

    /**
     * @param mixed $metrics
     */
    public function setMetrics($metrics);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return null|int
     */
    public function getPublisherId();

    /**
     * @return PublisherInterface|null
     */
    public function getPublisher();

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher(PublisherInterface $publisher);

    /**
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSources();

    /**
     * @param mixed $connectedDataSources
     * @return self
     */
    public function setConnectedDataSources($connectedDataSources);

    /**
     * @return array
     */
    public function getActions();

    /**
     * @param mixed $actions
     * @return self
     */
    public function setActions($actions);

    /**
     * @return mixed
     */
    public function getAllowOverwriteExistingData();

    /**
     * @param mixed $allowOverwriteExistingData
     * @return self
     */
    public function setAllowOverwriteExistingData($allowOverwriteExistingData);

    /**
     * @return array
     */
    public function getAllDimensionMetrics();

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
     * @return int
     */
    public function getTotalRow();

    /**
     * @param int $totalRow
     * @return self
     */
    public function setTotalRow($totalRow);

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
     * @return int
     */
    public function getNumConnectedDataSourceChanges();

    /**
     * @param int $noConnectedDataSourceChanges
     * @return self
     */
    public function setNumConnectedDataSourceChanges($noConnectedDataSourceChanges);

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
     * @return boolean
     */
    public function isMapBuilderEnabled();

    /**
     * @param boolean $mapBuilderEnabled
     * @return self
     */
    public function setMapBuilderEnabled($mapBuilderEnabled);

    /**
     * @return array
     */
    public function getMapBuilderConfigs();

    /**
     * @param array $mapBuilderConfigs
     * @return self
     */
    public function setMapBuilderConfigs($mapBuilderConfigs);

    /**
     * @param int $numChanges
     * @return $this
     */
    public function increaseNumChanges($numChanges = 1);

    /**
     * decrease NumChanges, minimum value is always 0
     *
     * @param int $numChanges
     * @return $this
     */
    public function decreaseNumChanges($numChanges = 1);

    /**
     * @param int $numChanges
     * @return $this
     */
    public function increaseNumConnectedDataSourceChanges($numChanges = 1);

    /**
     * decrease NoConnectedDataSourceChanges, minimum value is always 0
     *
     * @param int $numChanges
     * @return $this
     */
    public function decreaseNumConnectedDataSourceChanges($numChanges = 1);

    /**
     * @return boolean
     */
    public function hasNonUpToDateMappedDataSets();

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return bool
     */
    public function hasNonUpToDateMappedDataSetsByConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource);
}