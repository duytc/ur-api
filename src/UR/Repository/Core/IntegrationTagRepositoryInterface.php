<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

interface IntegrationTagRepositoryInterface extends ObjectRepository
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
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);
}