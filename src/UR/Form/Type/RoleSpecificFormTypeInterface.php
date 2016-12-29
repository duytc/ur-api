<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormTypeInterface;
use UR\Model\User\Role\UserRoleInterface;

interface RoleSpecificFormTypeInterface extends FormTypeInterface
{
    public function setUserRole(UserRoleInterface $userRole);
}