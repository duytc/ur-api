<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\DataSourceInterface;
use UR\Model\User\UserEntityInterface;

class DataSourceVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = DataSourceInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param DataSourceInterface $dataSource
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($dataSource, UserEntityInterface $user, $action)
    {
        return $user->getId() == $dataSource->getPublisherId();
    }
}