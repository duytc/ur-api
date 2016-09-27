<?php

namespace Tagcade\Form\Type;

use Symfony\Component\Form\FormTypeInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

interface RoleSpecificFormTypeInterface extends FormTypeInterface
{
    public function setUserRole(UserRoleInterface $userRole);
}