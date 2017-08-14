<?php

namespace UR\Model\Core;

class IntegrationTag implements IntegrationTagInterface
{
    protected $id;

    /** @var  TagInterface */
    protected $tag;

    /**
     * @var IntegrationInterface
     */
    protected $integration;

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
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @inheritdoc
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return IntegrationInterface
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @param IntegrationInterface $integration
     * @return self
     */
    public function setIntegration($integration)
    {
        $this->integration = $integration;

        return $this;
    }
}