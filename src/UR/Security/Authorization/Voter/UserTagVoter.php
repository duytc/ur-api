<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\UserTagInterface;
use UR\Model\User\UserEntityInterface;

class UserTagVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = UserTagInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param UserTagInterface $userTag
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($userTag, UserEntityInterface $user, $action)
    {
        return $user->getId() == $userTag->getPublisher()->getId();
    }
}