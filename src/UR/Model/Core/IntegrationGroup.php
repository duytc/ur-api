<?php

namespace UR\Model\Core;

use Doctrine\Common\Collections\Collection;

class IntegrationGroup implements IntegrationGroupInterface
{
    protected $id;

    protected $name;

    /** @var  array|IntegrationInterface[] */
    protected $integrations;

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
    public function getIntegrations()
    {
        if ($this->integrations instanceof Collection) {
            return $this->integrations->toArray();
        }

        return $this->integrations;
    }

    /**
     * @inheritdoc
     */
    public function setIntegrations(array $integrations)
    {
        $this->integrations = $integrations;

        return $this;
    }
}