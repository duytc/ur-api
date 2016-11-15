<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class ImportHistoryHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return ImportHistoryManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}