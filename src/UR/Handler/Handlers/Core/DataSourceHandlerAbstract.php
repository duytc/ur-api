<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\DataSourceManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSourceHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return DataSourceManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}