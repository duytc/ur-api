<?php


namespace UR\Security\Authorization\Voter;


use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\ModelInterface;
use UR\Model\User\UserEntityInterface;

class OptimizationRuleVoter extends EntityVoterAbstract
{


    /**
     * Checks to see if a publisher has permission to perform an action
     *
     * @param OptimizationRuleInterface $entity
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($entity, UserEntityInterface $user, $action)
    {
        return $entity->getPublisher()->getId() == $user->getId();
    }

    /**
     * Checks if the voter supports the given class.
     *
     * @param string $class A class name
     *
     * @return bool true if this Voter can process the class
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function supportsClass($class)
    {
        $supportedClass = OptimizationRuleInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }
}