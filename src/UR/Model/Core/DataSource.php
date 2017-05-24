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
    protected $detectedFields;
    protected $nextAlertTime;
    protected $useIntegration; // bool, true if use integration
    protected $lastActivity;
    protected $numOfFiles;

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
        $this->numOfFiles = 0;
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
        return $this;
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
        return $this;
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
        return $this;
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
        return $this;
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
        return $this;
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
        return $this;
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
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDetectedFields()
    {
        return $this->detectedFields;
    }

    /**
     * @inheritdoc
     */
    public function setDetectedFields($detectedFields)
    {
        $this->detectedFields = $detectedFields;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUseIntegration()
    {
        return $this->useIntegration;
    }

    /**
     * @inheritdoc
     */
    public function setUseIntegration($useIntegration)
    {
        $this->useIntegration = $useIntegration;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNextAlertTime()
    {
        return $this->nextAlertTime;
    }

    /**
     * @inheritdoc
     */
    public function setNextAlertTime($nextAlertTime)
    {
        $this->nextAlertTime = $nextAlertTime;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLastActivity()
    {
        return $this->lastActivity;
    }

    /**
     * @inheritdoc
     */
    public function setLastActivity($lastActivity)
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumOfFiles()
    {
        return $this->numOfFiles;
    }

    /**
     * @inheritdoc
     */
    public function setNumOfFiles($numOfFiles)
    {
        $this->numOfFiles = $numOfFiles;
        return $this;
    }
}