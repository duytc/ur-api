<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Behaviors\CreateUrApiKeyTrait;
use UR\Behaviors\CreateUrEmailTrait;
use UR\Entity\Core\DataSource;

class DataSourceCreatedListener
{
    use CreateUrEmailTrait;

    use CreateUrApiKeyTrait;

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
            $urEmail = $this->generateUniqueUrEmail($entity->getPublisherId(), $this->urEmailTemplate);
            $entity->setUrEmail($urEmail);
            
            $apiKey = $this->generateUrApiKey($entity->getPublisher()->getUsername());
            $entity->setApiKey($apiKey);
        }
    }
}