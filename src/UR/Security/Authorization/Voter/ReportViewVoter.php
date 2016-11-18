<?php

namespace UR\Security\Authorization\Voter;

use UR\Model\Core\ReportViewInterface;
use UR\Model\User\UserEntityInterface;

class ReportViewVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = ReportViewInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ReportViewInterface $reportView
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($reportView, UserEntityInterface $user, $action)
    {
        return $user->getId() == $reportView->getPublisher()->getId();
    }
}