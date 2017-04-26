<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface AlertInterface extends ModelInterface
{
    /* define all alert codes */
    // TODO: move all other alert codes to here...
    const ALERT_CODE_BROWSER_AUTOMATION_LOGIN_FAIL = 2001;
    const ALERT_CODE_BROWSER_AUTOMATION_TIME_OUT = 2002;

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
     * @param mixed $code
     */
    public function setCode($code);

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

    /**
     * @return mixed
     */
    public function getDetail();

    /**
     * @param mixed $detail
     */
    public function setDetail($detail);
}