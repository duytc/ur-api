<?php

namespace UR\Bundle\UserSystem\AdminBundle\Entity;

use UR\Bundle\UserBundle\Entity\User as BaseUser;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\UserEntityInterface;

class User extends BaseUser implements AdminInterface
{
    protected $id;
    protected $settings;

    /**
     * @return UserEntityInterface
     */
    public function getUser()
    {
        return $this;
    }
}
