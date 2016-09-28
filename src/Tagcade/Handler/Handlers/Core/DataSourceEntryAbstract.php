<?php

namespace Tagcade\Handler\Handlers\Core;

use Tagcade\DomainManager\DataSourceEntryManagerInterface;
use Tagcade\Handler\RoleHandlerAbstract;

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