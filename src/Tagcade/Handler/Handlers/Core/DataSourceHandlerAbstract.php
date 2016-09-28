<?php

namespace Tagcade\Handler\Handlers\Core;


use Tagcade\DomainManager\DataSourceManagerInterface;
use Tagcade\Handler\RoleHandlerAbstract;

abstract class DataSourceHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return DataSourceManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}