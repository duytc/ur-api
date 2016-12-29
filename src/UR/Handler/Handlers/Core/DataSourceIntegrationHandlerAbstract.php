<?php

namespace UR\Handler\Handlers\Core;

use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSourceIntegrationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return DataSourceIntegrationManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}