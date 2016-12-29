<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\DataSetManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSetHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return DataSetManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}