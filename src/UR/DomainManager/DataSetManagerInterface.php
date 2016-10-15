<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSetManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSetInterface[]
     */
    public function getDataSetForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}