<?php


namespace UR\Handler\Handlers\Core;


use UR\Handler\RoleHandlerAbstract;

abstract class AutoOptimizationConfigHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}