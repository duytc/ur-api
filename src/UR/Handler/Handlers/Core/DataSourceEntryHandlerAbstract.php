<?php

namespace UR\Handler\Handlers\Core;

use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class DataSourceEntryHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return DataSourceEntryManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}