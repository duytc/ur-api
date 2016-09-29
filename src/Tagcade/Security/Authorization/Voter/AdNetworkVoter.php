<?php

namespace Tagcade\Security\Authorization\Voter;

use Tagcade\Model\Core\AdNetworkInterface;
use Tagcade\Model\User\UserEntityInterface;

class AdNetworkVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = AdNetworkInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param AdNetworkInterface $dataSource
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($dataSource, UserEntityInterface $user, $action)
    {
        return $user->getId() == $dataSource->getPublisherId();
    }
}