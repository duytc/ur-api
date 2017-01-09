<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSetInterface extends ModelInterface
{
    const UNIQUE_ID_COLUMN = '__unique_id';
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
     * @return mixed
     */
    public function getDimensions();

    /**
     * @param mixed $dimensions
     */
    public function setDimensions($dimensions);

    /**
     * @return mixed
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
}