<?php

namespace UR\Handler\Handlers\Core\Publisher;

use UR\Handler\Handlers\Core\DataSourceIntegrationHandlerAbstract;
use UR\Model\User\Role\UserRoleInterface;
use UR\Model\User\Role\PublisherInterface;

class DataSourceIntegrationHandler extends DataSourceIntegrationHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }
}