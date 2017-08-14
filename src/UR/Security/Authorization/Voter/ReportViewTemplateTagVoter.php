<?php

namespace UR\Security\Authorization\Voter;

use UR\DomainManager\ReportViewTemplateTagManagerInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class ReportViewTemplateTagVoter extends EntityVoterAbstract
{
    /** @var ReportViewTemplateTagManagerInterface  */
    private $reportViewTemplateTagManager;

    /**
     * ReportViewTemplateTagVoter constructor.
     * @param ReportViewTemplateTagManagerInterface $reportViewTemplateTagManager
     */
    public function __construct(ReportViewTemplateTagManagerInterface $reportViewTemplateTagManager)
    {
        $this->reportViewTemplateTagManager = $reportViewTemplateTagManager;
    }

    public function supportsClass($class)
    {
        $supportedClass = ReportViewTemplateTagInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ReportViewTemplateTagInterface $reportViewTemplateTag
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($reportViewTemplateTag, UserEntityInterface $user, $action)
    {
        if (!in_array($action, array(EntityVoterAbstract::VIEW, EntityVoterAbstract::EDIT))) {
            return false;
        }

        if ($action == EntityVoterAbstract::EDIT) return false;

        /** @var PublisherInterface $user*/
        $reportViewTemplateTag = $this->reportViewTemplateTagManager->findByPublisher($user);
        if ($reportViewTemplateTag instanceof ReportViewTemplateTagInterface) return true;

        return false;
    }
}