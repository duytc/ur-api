<?php

namespace UR\Bundle\AdminApiBundle\Handler;

use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Handler\HandlerAbstract;

/**
 * Not using a RoleHandlerInterface because this Handler is local
 * to the admin api bundle. All routes to this bundle are protected in app/config/security.yml
 */
class UserHandler extends HandlerAbstract implements UserHandlerInterface
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return PublisherManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }

    /**
     * @inheritdoc
     */
    public function allPublishers()
    {
        return $this->getDomainManager()->allPublishers();
    }

    public function allActivePublishers()
    {
        return $this->getDomainManager()->allActivePublishers();
    }
}