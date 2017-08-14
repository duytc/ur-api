<?php

namespace UR\DomainManager;

use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

interface IntegrationTagManagerInterface extends ManagerInterface
{
    /**
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function findByIntegration(IntegrationInterface $integration);

    /**
     * @param TagInterface $tag
     * @return mixed
     */
    public function findByTag(TagInterface $tag);

    /**
     * @param PublisherInterface $publisher
     */
    public function findByPublisher(PublisherInterface $publisher);
}