<?php

namespace UR\Handler\Handlers\Core\Publisher;

use UR\Handler\Handlers\Core\DataSourceIntegrationScheduleHandlerAbstract;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSourceIntegrationScheduleHandler extends DataSourceIntegrationScheduleHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }
}