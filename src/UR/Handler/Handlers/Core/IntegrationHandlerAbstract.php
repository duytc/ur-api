<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\IntegrationManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class IntegrationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return IntegrationManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}