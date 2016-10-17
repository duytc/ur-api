<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class ConnectedDataSourceHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return ConnectedDataSourceManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}