<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\User\UserEntityInterface;

class AutoOptimizationConfigVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = AutoOptimizationConfigInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($autoOptimizationConfig, UserEntityInterface $user, $action)
    {
        return $user->getId() == $autoOptimizationConfig->getPublisher()->getId();
    }
}