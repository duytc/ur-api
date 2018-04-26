<?php


namespace UR\Handler\Handlers\Core;


use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class OptimizationIntegrationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return OptimizationIntegrationManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}