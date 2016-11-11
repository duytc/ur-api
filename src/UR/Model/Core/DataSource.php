<?php

namespace UR\Model\Core;

use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class DataSource implements DataSourceInterface
{
    protected $id;
    protected $name;
    protected $format;
    protected $alertSetting;
    protected $apiKey;
    protected $urEmail;
    protected $enable;
    const UR_EMAIL = "@unifiedreportemail";

    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var DataSourceEntryInterface[]
     */
    protected $dataSourceEntries;

    /**
     * @var IntegrationInterface[]
     */
    protected $dataSourceIntegrations;

    /**
     * @var ConnectedDataSourceInterface[]
     */
    protected $connectedDataSources;

    public function __construct()
    {
        $this->enable = true;
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
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPublisherId()
    {
        if (!$this->publisher) {
            return null;
        }

        return $this->publisher->getId();
    }

    /**
     * @inheritdoc
     */
    public function setPublisher(PublisherInterface $publisher)
    {
        $this->publisher = $publisher->getUser();
        return $this;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @inheritdoc
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceIntegrations()
    {
        return $this->dataSourceIntegrations;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceIntegrations($dataSourceIntegrations)
    {
        $this->dataSourceIntegrations = $dataSourceIntegrations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @inheritdoc
     */
    public function setApiKey($apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @inheritdoc
     */
    public function getUrEmail()
    {
        return $this->urEmail;
    }

    /**
     * @inheritdoc
     */
    public function setUrEmail($urEmail)
    {
        $this->urEmail = $urEmail;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntries()
    {
        return $this->dataSourceEntries;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceEntries($dataSourceEntries)
    {
        $this->dataSourceEntries = $dataSourceEntries;
    }

    /**
     * @inheritdoc
     */
    public function getAlertSetting()
    {
        return $this->alertSetting;
    }

    /**
     * @inheritdoc
     */
    public function setAlertSetting($alertSetting)
    {
        $this->alertSetting = $alertSetting;
    }

    /**
     * @inheritdoc
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * @inheritdoc
     */
    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSources()
    {
        return $this->connectedDataSources;
    }

    /**
     * @inheritdoc
     */
    public function setConnectedDataSources($connectedDataSources)
    {
        $this->connectedDataSources = $connectedDataSources;
    }
}