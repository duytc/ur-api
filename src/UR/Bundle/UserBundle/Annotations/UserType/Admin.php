<?php

namespace UR\Bundle\UserBundle\Annotations\UserType;

use Doctrine\Common\Annotations\Annotation;

use UR\Model\User\Role\AdminInterface;

/**
 * @Annotation
 * @Target({"METHOD","CLASS"})
 */
class Admin implements UserTypeInterface
{
    public function getUserClass()
    {
        return AdminInterface::class;
    }
}