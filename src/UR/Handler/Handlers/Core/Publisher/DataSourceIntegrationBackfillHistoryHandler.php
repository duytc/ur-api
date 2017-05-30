<?php

namespace UR\Handler\Handlers\Core\Publisher;

use UR\Handler\Handlers\Core\DataSourceIntegrationBackfillHistoryHandlerAbstract;
use UR\Model\User\Role\UserRoleInterface;
use UR\Model\User\Role\PublisherInterface;

class DataSourceIntegrationBackfillHistoryHandler extends DataSourceIntegrationBackfillHistoryHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }
}