<?php


namespace UR\Handler\Handlers\Core;


use UR\Handler\RoleHandlerAbstract;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\User\Role\UserRoleInterface;

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