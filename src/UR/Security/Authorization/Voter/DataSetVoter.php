<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\DataSetInterface;
use UR\Model\User\UserEntityInterface;

class DataSetVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = DataSetInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param DataSetInterface $dataSet
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($dataSet, UserEntityInterface $user, $action)
    {
        return $user->getId() == $dataSet->getPublisherId();
    }
}