<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\ImportHistoryInterface;
use UR\Model\User\UserEntityInterface;

class ImportHistoryVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = ImportHistoryInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ImportHistoryInterface $importHistory
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($importHistory, UserEntityInterface $user, $action)
    {
        return $user->getId() == $importHistory->getDataSet()->getPublisherId();
    }
}