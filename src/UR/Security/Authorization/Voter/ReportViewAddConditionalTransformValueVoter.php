<?php

namespace UR\Security\Authorization\Voter;

use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\User\UserEntityInterface;

class ReportViewAddConditionalTransformValueVoter extends EntityVoterAbstract
{
    public function supportsClass($class)
    {
        $supportedClass = ReportViewAddConditionalTransformValue::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ReportViewAddConditionalTransformValueInterface $reportViewAddConditionalTransformValue
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($reportViewAddConditionalTransformValue, UserEntityInterface $user, $action)
    {
        return $user->getId() == $reportViewAddConditionalTransformValue->getPublisher()->getId();
    }
}