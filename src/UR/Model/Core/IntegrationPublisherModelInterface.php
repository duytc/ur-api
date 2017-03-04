<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;

interface IntegrationPublisherModelInterface extends ModelInterface
{
    /**
     * @return Integration
     */
    public function getIntegration();

    /**
     * @param Integration $integration
     */
    public function setIntegration($integration);

    /**
     * @return User
     */
    public function getPublisher();

    /**
     * @param User $publisher
     */
    public function setPublisher($publisher);
}