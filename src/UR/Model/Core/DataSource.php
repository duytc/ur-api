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

    /** @var UserEntityInterface */
    protected $publisher;

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
     * @var IntegrationInterface[]
     */
    protected $dataSourceIntegrations;

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
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @inheritdoc
     */
    public function generateApiKey(){
        if($this->apiKey==null){
            $tokenString = $this->getPublisher()->getUsername() . md5(uniqid(rand(),true));
            $this->apiKey=$tokenString;
        }
        return $this->apiKey;
    }
}