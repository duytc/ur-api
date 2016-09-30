<?php

namespace UR\Model\Core;

class Integration implements IntegrationInterface
{
    protected $id;
    protected $name;
    protected $type;
    protected $url;

    /**
     * @var IntegrationGroupInterface
     */
    protected $integrationGroup;

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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @inheritdoc
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIntegrationGroup()
    {
        return $this->integrationGroup;
    }

    /**
     * @inheritdoc
     */
    public function setIntegrationGroup(IntegrationGroupInterface $integrationGroup)
    {
        $this->integrationGroup = $integrationGroup;

        return $this;
    }
}