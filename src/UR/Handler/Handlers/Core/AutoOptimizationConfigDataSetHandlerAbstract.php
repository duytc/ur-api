<?php


namespace UR\Handler\Handlers\Core;


use UR\Handler\RoleHandlerAbstract;

abstract class AutoOptimizationConfigDataSetHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}