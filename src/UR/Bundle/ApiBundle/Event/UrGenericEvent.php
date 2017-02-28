<?php
namespace UR\Bundle\ApiBundle\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class UrGenericEvent extends GenericEvent
{
    /**
     * Encapsulate an event with $subject and $args.
     *
     * @param mixed $subject   The subject of the event, usually an object
     * @param array $arguments Arguments to store in the event
     */
    public function __construct(UrEvent $subject, array $arguments = array())
    {
        parent::__construct($subject, $arguments);
    }
}