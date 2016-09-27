<?php

namespace Tagcade\Handler\Handlers\Core;

use Tagcade\DomainManager\AdNetworkManagerInterface;
use Tagcade\Handler\RoleHandlerAbstract;

abstract class AdNetworkHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @inheritdoc
     *
     * Auto complete helper method
     *
     * @return AdNetworkManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }
}