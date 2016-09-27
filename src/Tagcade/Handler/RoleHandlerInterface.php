<?php

namespace Tagcade\Handler;

use Tagcade\Model\User\Role\UserRoleInterface;
use Tagcade\Exception\LogicException;

interface RoleHandlerInterface extends HandlerInterface
{
    /**
     * @param UserRoleInterface $role
     * @return bool
     */
    public function supportsRole(UserRoleInterface $role);

    public function setUserRole(UserRoleInterface $userRole);

    /**
     * @return UserRoleInterface
     * @throws LogicException
     */
    public function getUserRole();
}