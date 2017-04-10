<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSetInterface extends ModelInterface
{
    const ID_COLUMN = '__id';
    const DATA_SOURCE_ID_COLUMN = '__data_source_id';
    const IMPORT_ID_COLUMN = '__import_id';
    const UNIQUE_ID_COLUMN = '__unique_id';
    const OVERWRITE_DATE = '__overwrite_date';
    const UNIQUE_HASH_INX = 'unique_hash_idx';

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
     * @return IntegrationInterface[]
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
     */
    public function setConnectedDataSources($connectedDataSources);

    /**
     * @return mixed
     */
    public function getActions();

    /**
     * @param mixed $actions
     */
    public function setActions($actions);

    /**
     * @return mixed
     */
    public function getAllowOverwriteExistingData();

    /**
     * @param mixed $allowOverwriteExistingData
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
     */
    public function setLinkedMapDataSets(array $linkedMapDataSets);

    /**
     * @return array
     */
    public function getDataSetImportJobs();

    /**
     * @param array $dataSetImportJobs
     */
    public function setDataSetImportJobs(array $dataSetImportJobs);

    /**
     * @param DataSetImportJobInterface $dataSetImportJob
     */
    public function addDataSetImportJobs(DataSetImportJobInterface $dataSetImportJob);
}