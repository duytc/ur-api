<?php


namespace UR\Service\DataSource;


use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;

interface IntegrationTagServiceInterface
{
    /**
     * @param IntegrationInterface $integration
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function createIntegrationTagForUser(IntegrationInterface $integration, PublisherInterface $publisher);

    public function updateIntegrationTagForUser(IntegrationInterface $integration, PublisherInterface $publisher);
}