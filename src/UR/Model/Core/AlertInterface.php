<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface AlertInterface extends ModelInterface
{
    //UPLOAD 10-20
    const UPLOAD_DATA_SUCCESS = 10;
    const UPLOAD_DATA_FAILURE = 11;
    const UPLOAD_DATA_WARNING = 12;


    //IMPORT 0-9
    const IMPORT_DATA_SUCCESS = 0;
    const REQUIRE_FAIL = 1;
    const FILTER_FAIL = 2;
    const TRANSFORM_FAIL = 3;
    const UNKNOWN_FAIL = 4;

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
    public function getCode();

    /**
     * @param mixed $type
     */
    public function setCode($type);

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
     * @return PublisherInterface
     */
    public function getPublisher();

    /**
     * @param PublisherInterface $publisher
     */
    public function setPublisher($publisher);
}