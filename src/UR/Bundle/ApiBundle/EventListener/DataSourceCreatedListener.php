<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Entity\Core\DataSource;

class DataSourceCreatedListener
{
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof DataSource) {
            $tokenString = str_replace(".", "", uniqid(rand(1, 10000), true));
            $entity->setUrEmail($tokenString . $entity::UR_EMAIL);
            $entity->setApiKey($entity->getPublisher()->getUsername() . $tokenString);
        }
    }

}