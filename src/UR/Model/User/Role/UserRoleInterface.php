<?php

namespace UR\Model\User\Role;

use UR\Model\User\UserEntityInterface;

interface UserRoleInterface
{
    /**
     * @return UserEntityInterface
     */
    public function getUser();

    /**
     * @return int|null
     */
    public function getId();
}