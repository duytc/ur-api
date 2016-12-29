<?php

namespace UR\Bundle\UserBundle\Annotations\UserType;

use Doctrine\Common\Annotations\Annotation;

use UR\Model\User\Role\PublisherInterface;

/**
 * @Annotation
 * @Target({"METHOD","CLASS"})
 */
class Publisher implements UserTypeInterface
{
    public function getUserClass()
    {
        return PublisherInterface::class;
    }
}