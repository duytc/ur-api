<?php

namespace UR\DomainManager;

use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

interface ReportViewTemplateManagerInterface extends ManagerInterface
{
    /**
     * @param TagInterface $tag
     */
    public function findByTag(TagInterface $tag);

    /**
     * @param PublisherInterface $publisher
     */
    public function findByPublisher(PublisherInterface $publisher);
}