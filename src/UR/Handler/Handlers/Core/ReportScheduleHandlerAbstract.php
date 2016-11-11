<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ReportScheduleManagerInterface;
use UR\Handler\RoleHandlerAbstract;

abstract class ReportScheduleHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return ReportScheduleManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}