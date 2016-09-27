<?php

namespace Tagcade\Bundle\UserSystem\AdminBundle\Entity;

use Tagcade\Bundle\UserBundle\Entity\User as BaseUser;
use Tagcade\Model\User\Role\AdminInterface;
use Tagcade\Model\User\UserEntityInterface;

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
