<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\User\UserEntityInterface;

class ConnectedDataSourceVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = ConnectedDataSourceInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($connectedDataSource, UserEntityInterface $user, $action)
    {
        return ($user->getId() == $connectedDataSource->getDataSet()->getPublisherId()) && ($user->getId() == $connectedDataSource->getDataSource()->getPublisher()->getId());
    }
}