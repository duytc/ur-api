<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\UserTagManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class UserTagHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return UserTagManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}