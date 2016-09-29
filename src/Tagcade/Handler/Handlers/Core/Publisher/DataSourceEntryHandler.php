<?php

namespace Tagcade\Handler\Handlers\Core\Publisher;

use Tagcade\Handler\Handlers\Core\DataSourceEntryHandlerAbstract;
use Tagcade\Model\User\Role\UserRoleInterface;
use Tagcade\Model\User\Role\PublisherInterface;

class DataSourceEntryHandler extends DataSourceEntryHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }
}