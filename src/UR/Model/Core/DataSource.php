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
    const UR_EMAIL= "@unifiedreportemail";

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
        if ($apiKey !== null) {
            $this->apiKey = $apiKey;
        } else {
            $this->apiKey = $this->getPublisher()->getUsername() . $this->generateApiKey();
        }
    }

    /**
     * @inheritdoc
     */
    public function generateApiKey()
    {
        $tokenString = md5(uniqid(rand(1, 10000), true));

        return $tokenString;
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
        if ($urEmail !== null) {
            $this->urEmail = $urEmail;
        } else {
            $this->urEmail = $this->generateApiKey() . self::UR_EMAIL;
        }
    }
}