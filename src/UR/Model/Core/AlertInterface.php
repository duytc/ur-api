<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface AlertInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getType();

    /**
     * @param mixed $type
     */
    public function setType($type);

    /**
     * @return mixed
     */
    public function getIsRead();

    /**
     * @param mixed $isRead
     */
    public function setIsRead($isRead);

    /**
     * @return mixed
     */
    public function getTitle();

    /**
     * @param mixed $title
     */
    public function setTitle($title);

    /**
     * @return mixed
     */
    public function getMessage();

    /**
     * @param mixed $message
     */
    public function setMessage($message);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return DataSourceEntryInterface
     */
    public function getDataSourceEntry();

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    public function setDataSourceEntry($dataSourceEntry);
}