<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\User\Role\UserRoleInterface;

interface IntegrationRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $user
     * @return QueryBuilder
     */
    public function getIntegrationsForUserQuery(UserRoleInterface $user);
}