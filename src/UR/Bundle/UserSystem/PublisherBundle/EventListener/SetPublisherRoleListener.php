<?php

namespace UR\Bundle\UserSystem\PublisherBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class SetPublisherRoleListener
{
    const ROLE_PUBLISHER = 'ROLE_PUBLISHER';

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof PublisherInterface) {
            return;
        }

        /**
         * @var UserEntityInterface $entity
         */

        $entity->setUserRoles(array(static::ROLE_PUBLISHER));
    }
}