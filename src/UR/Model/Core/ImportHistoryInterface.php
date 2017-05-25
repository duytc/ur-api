<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ImportHistoryInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return mixed
     */
    public function getDescription();

    /**
     * @param mixed $description
     */
    public function setDescription($description);

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     */
    public function setDataSet($dataSet);

    /**
     * @return DataSourceEntryInterface
     */
    public function getDataSourceEntry();

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    public function setDataSourceEntry($dataSourceEntry);

    /**
     * @return ConnectedDataSourceInterface|null
     */
    public function getConnectedDataSource();

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return self
     */
    public function setConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource);
}