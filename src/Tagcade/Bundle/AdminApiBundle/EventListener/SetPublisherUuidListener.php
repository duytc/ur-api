<?php


namespace Tagcade\Bundle\AdminApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Ramsey\Uuid\Uuid;
use Tagcade\Exception\LogicException;
use Tagcade\Model\User\Role\PublisherInterface;

class SetPublisherUuidListener
{
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof PublisherInterface) {
            return;
        }

        try {
            $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, $entity->getEmail());
            $entity->setUuid($uuid5->toString());
        } catch(UnsatisfiedDependencyException $e) {
            throw new LogicException($e->getMessage());
        }
    }
}