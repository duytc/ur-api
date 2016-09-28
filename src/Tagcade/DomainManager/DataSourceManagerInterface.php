<?php

namespace Tagcade\DomainManager;


use Tagcade\Model\Core\DataSourceInterface;
use Tagcade\Model\User\Role\PublisherInterface;

interface DataSourceManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSourceInterface[]
     */
    public function getDataSourceForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}