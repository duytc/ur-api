<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\DataSourceManagerInterface;
use UR\DomainManager\IntegrationGroupManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class IntegrationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return IntegrationGroupManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}