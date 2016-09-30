<?php

namespace UR\Handler\Handlers\Core;

use UR\DomainManager\AdNetworkManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class AdNetworkHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return AdNetworkManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}