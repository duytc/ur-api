<?php

namespace UR\Handler\Handlers\Core;

use UR\DomainManager\DataSourceIntegrationScheduleManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSourceIntegrationScheduleHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return DataSourceIntegrationScheduleManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}