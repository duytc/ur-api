<?php

namespace UR\DomainManager;

use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

interface UserTagManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param TagInterface $tag
     * @return mixed
     */
    public function findByTag(TagInterface $tag);
}