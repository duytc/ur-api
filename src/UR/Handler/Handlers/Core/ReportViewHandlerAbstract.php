<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ReportViewManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class ReportViewHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return ReportViewManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}