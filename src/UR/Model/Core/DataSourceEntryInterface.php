<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceEntryInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getReceivedDate();

    /**
     * @param mixed $receivedDate
     * @return self
     */
    public function setReceivedDate($receivedDate);

    /**
     * @return boolean
     */
    public function getValid();

    /**
     * @param boolean $valid
     * @return self
     */
    public function setValid($valid);

    /**
     * @return string
     */
    public function getPath();

    /**
     * @param string $path
     * @return self
     */
    public function setPath($path);

    /**
     * @return array
     */
    public function getMetaData();

    /**
     * @param array $metaData
     * @return self
     */
    public function setMetaData($metaData);

    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     * @return self
     */
    public function setDataSource(DataSourceInterface $dataSource);
}