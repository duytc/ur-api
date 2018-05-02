<?php


namespace UR\Handler\Handlers\Core;


use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class OptimizationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return OptimizationRuleManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}