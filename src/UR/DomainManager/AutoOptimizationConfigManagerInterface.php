<?php


namespace UR\DomainManager;


use UR\Model\User\Role\PublisherInterface;

interface AutoOptimizationConfigManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);
}