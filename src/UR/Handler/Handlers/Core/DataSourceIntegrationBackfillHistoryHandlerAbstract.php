<?php

namespace UR\Handler\Handlers\Core;

use UR\DomainManager\DataSourceIntegrationBackfillHistoryManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSourceIntegrationBackfillHistoryHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return DataSourceIntegrationBackfillHistoryManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}