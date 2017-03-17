<?php


namespace UR\Model\Core;


use UR\Model\User\Role\PublisherInterface;

class IntegrationPublisher implements IntegrationPublisherInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var IntegrationInterface $integration
     */
    protected $integration;

    /**
     * @var PublisherInterface $publisher
     */
    protected $publisher;

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
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @inheritdoc
     */
    public function setIntegration(IntegrationInterface $integration)
    {
        $this->integration = $integration;
        return $this;
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
    public function setPublisher(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
        return $this;
    }
}