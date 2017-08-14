<?php

namespace UR\Security\Authorization\Voter;

use UR\DomainManager\ReportViewTemplateManagerInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class ReportViewTemplateVoter extends EntityVoterAbstract
{
    /** @var ReportViewTemplateManagerInterface  */
    private $reportViewTemplateManager;

    /**
     * ReportViewTemplateVoter constructor.
     * @param ReportViewTemplateManagerInterface $reportViewTemplateManager
     */
    public function __construct(ReportViewTemplateManagerInterface $reportViewTemplateManager)
    {
        $this->reportViewTemplateManager = $reportViewTemplateManager;
    }

    public function supportsClass($class)
    {
        $supportedClass = ReportViewTemplateInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($reportViewTemplate, UserEntityInterface $user, $action)
    {
        if (!in_array($action, array(EntityVoterAbstract::VIEW, EntityVoterAbstract::EDIT))) {
            return false;
        }

        if ($action == EntityVoterAbstract::EDIT) return false;

        /** @var PublisherInterface $user*/
        $reportViewTemplates = $this->reportViewTemplateManager->findByPublisher($user);
        if (is_array($reportViewTemplates) && !empty($reportViewTemplates)) return true;

        return false;
    }

}