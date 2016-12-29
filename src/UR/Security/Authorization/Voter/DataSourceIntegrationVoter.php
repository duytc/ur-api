<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\User\UserEntityInterface;

class DataSourceIntegrationVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = DataSourceIntegrationInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceEntry
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($dataSourceEntry, UserEntityInterface $user, $action)
    {
        return $user->getId() == $dataSourceEntry->getDataSource()->getPublisherId();
    }
}