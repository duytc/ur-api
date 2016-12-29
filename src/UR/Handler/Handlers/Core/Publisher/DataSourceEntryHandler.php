<?php

namespace UR\Handler\Handlers\Core\Publisher;

use UR\Handler\Handlers\Core\DataSourceEntryHandlerAbstract;
use UR\Model\User\Role\UserRoleInterface;
use UR\Model\User\Role\PublisherInterface;

class DataSourceEntryHandler extends DataSourceEntryHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }
}