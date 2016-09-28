<?php

namespace Tagcade\Model\Core;

use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\PublisherInterface;

interface DataSourceEntryInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getReceivedDate();

    /**
     * @param mixed $receivedDate
     */
    public function setReceivedDate($receivedDate);

    /**
     * @return boolean
     */
    public function getValid();

    /**
     * @param boolean $valid
     */
    public function setValid($valid);

    /**
     * @return string
     */
    public function getPath();

    /**
     * @param string $path
     */
    public function setPath($path);

    /**
     * @return array
     */
    public function getMetaData();

    /**
     * @param array $metaData
     */
    public function setMetaData($metaData);

    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     */
    public function setDataSource($dataSource);
}