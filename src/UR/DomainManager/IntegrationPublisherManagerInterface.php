<?php

namespace UR\DomainManager;

use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;

interface IntegrationPublisherManagerInterface extends ManagerInterface
{
    /**
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function findByIntegration(IntegrationInterface $integration);

    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);
}