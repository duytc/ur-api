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
            $urEmail = str_replace('$PUBLISHER_ID$', $entity->getPublisherId(), $this->urEmailTemplate);

            $tokenString = str_replace(".", "", uniqid(rand(1, 10000), true));
            $urEmail = str_replace('$TOKEN$', $tokenString, $urEmail);

            $entity->setUrEmail($urEmail);
            $entity->setApiKey($entity->getPublisher()->getUsername() . $tokenString);
        }
    }
}