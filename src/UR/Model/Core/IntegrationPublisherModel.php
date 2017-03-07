<?php


namespace UR\Model\Core;


use UR\Entity\Core\Integration;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;

class IntegrationPublisherModel implements IntegrationPublisherModelInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var Integration $integration
     */
    protected $integration;

    /**
     * @var User $publisher
     */
    protected $publisher;

    /**
     * @return Integration
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @param Integration $integration
     */
    public function setIntegration($integration)
    {
        $this->integration = $integration;
    }

    /**
     * @return User
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @param User $publisher
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}