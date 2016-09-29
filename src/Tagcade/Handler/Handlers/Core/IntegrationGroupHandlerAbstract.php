<?php

namespace Tagcade\Handler\Handlers\Core;


use Tagcade\DomainManager\DataSourceManagerInterface;
use Tagcade\DomainManager\IntegrationGroupManagerInterface;
use Tagcade\Handler\RoleHandlerAbstract;

abstract class IntegrationGroupHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return IntegrationGroupManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}