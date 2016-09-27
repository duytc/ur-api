<?php

namespace Tagcade\Bundle\UserBundle\Annotations\UserType;

use Doctrine\Common\Annotations\Annotation;

use Tagcade\Model\User\Role\AdminInterface;

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