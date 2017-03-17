<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface IntegrationPublisherInterface extends ModelInterface
{
    /**
     * @return IntegrationInterface
     */
    public function getIntegration();

    /**
     * @param IntegrationInterface $integration
     * @return self
     */
    public function setIntegration(IntegrationInterface $integration);

    /**
     * @return PublisherInterface
     */
    public function getPublisher();

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher(PublisherInterface $publisher);
}