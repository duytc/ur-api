<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\User\UserEntityInterface;

class DataSourceEntryVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = DataSourceEntryInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($dataSourceEntry, UserEntityInterface $user, $action)
    {
        return $user->getId() == $dataSourceEntry->getDataSource()->getPublisherId();
    }
}