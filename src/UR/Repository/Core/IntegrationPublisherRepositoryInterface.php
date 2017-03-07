<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\Core\IntegrationInterface;

interface IntegrationPublisherRepositoryInterface extends ObjectRepository
{
    /**
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function getByIntegration(IntegrationInterface $integration);

    /**
     * @param User $publisher
     * @return mixed
     */
    public function getByPublisher(User $publisher);
}