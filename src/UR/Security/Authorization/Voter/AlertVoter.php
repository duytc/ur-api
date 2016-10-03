<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\AlertInterface;
use UR\Model\User\UserEntityInterface;

class AlertVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = AlertInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param AlertInterface $alert
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($alert, UserEntityInterface $user, $action)
    {
        return $user->getId() == $alert->getDataSourceEntry()->getDataSource()->getPublisherId();
    }
}