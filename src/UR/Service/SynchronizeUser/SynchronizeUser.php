<?php


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Model\User\Role\PublisherInterface;

class SynchronizeUser
{
    private $publisherManager;

    public function __construct(PublisherManagerInterface $publisherManager)
    {
        $this->publisherManager = $publisherManager;
    }

    public function updateUser(PublisherInterface $publisher)
    {
        $this->publisherManager->save($publisher);
    }
}