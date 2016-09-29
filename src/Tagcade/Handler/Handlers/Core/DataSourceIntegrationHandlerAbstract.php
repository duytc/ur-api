<?php

namespace Tagcade\Handler\Handlers\Core;

use Tagcade\DomainManager\DataSourceIntegrationManagerInterface;
use Tagcade\Handler\RoleHandlerAbstract;

abstract class DataSourceIntegrationHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return DataSourceIntegrationManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}