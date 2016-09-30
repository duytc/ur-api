<?php

namespace UR\Handler;

use UR\Model\User\Role\UserRoleInterface;
use UR\Exception\LogicException;

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