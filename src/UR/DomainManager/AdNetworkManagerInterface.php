<?php

namespace UR\DomainManager;

use UR\Model\Core\AdNetworkInterface;
use UR\Model\User\Role\PublisherInterface;

interface AdNetworkManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return AdNetworkInterface[]
     */
    public function getAdNetworksForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}