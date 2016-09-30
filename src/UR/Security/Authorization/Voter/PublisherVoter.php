<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class PublisherVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = PublisherInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param PublisherInterface $account
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($account, UserEntityInterface $user, $action)
    {
        $publisherId = $account->getId();

        return $user->getId() == $publisherId;
    }
}