<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSourceInterface[]
     */
    public function getDataSourceForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param string $apiKey
     * @return DataSourceInterface
     */
    public function getDataSourceByApiKey($apiKey);
}