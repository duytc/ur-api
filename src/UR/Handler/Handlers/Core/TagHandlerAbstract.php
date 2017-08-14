<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\AlertManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class TagHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return AlertManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}