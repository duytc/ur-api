<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Entity\Core\DataSource;

class DataSourceCreatedListener
{
    protected $urEmailTemplate;

    /**
     * DataSourceCreatedListener constructor.
     * @param string $urEmailTemplate
     */
    public function __construct($urEmailTemplate)
    {
        $this->urEmailTemplate = $urEmailTemplate;
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof DataSource) {
            $tokenString = str_replace(".", "", uniqid(rand(1, 10000), true));
            $entity->setUrEmail(str_replace('$TOKEN$', $tokenString, $this->urEmailTemplate));
            $entity->setApiKey($entity->getPublisher()->getUsername() . $tokenString);
        }
    }

}