<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\AdNetworkInterface;
use UR\Model\User\UserEntityInterface;

class AdNetworkVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = AdNetworkInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param AdNetworkInterface $adNetwork
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($adNetwork, UserEntityInterface $user, $action)
    {
        return $user->getId() == $adNetwork->getPublisherId();
    }
}