<?php

namespace Tagcade\Model\Core;

use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\UserEntityInterface;

class DataSourceIntegration implements DataSourceIntegrationInterface
{
    protected $id;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var IntegrationInterface
     */
    protected $integration;

    protected $username;
    protected $password;
    protected $schedule;

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @inheritdoc
     */
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    /**
     * @inheritdoc
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @inheritdoc
     */
    public function setIntegration($integration)
    {
        $this->integration = $integration;
    }

    /**
     * @inheritdoc
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @inheritdoc
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @inheritdoc
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @inheritdoc
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @inheritdoc
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @inheritdoc
     */
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;
    }
}