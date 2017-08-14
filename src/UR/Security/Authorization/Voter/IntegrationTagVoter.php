<?php

namespace UR\Security\Authorization\Voter;

use UR\DomainManager\IntegrationTagManagerInterface;
use UR\Model\Core\IntegrationTagInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class IntegrationTagVoter extends EntityVoterAbstract
{
    /** @var IntegrationTagManagerInterface  */
    private $integrationTagManager;

    /**
     * IntegrationTagVoter constructor.
     * @param IntegrationTagManagerInterface $integrationTagManager
     */
    public function __construct(IntegrationTagManagerInterface $integrationTagManager)
    {
        $this->integrationTagManager = $integrationTagManager;
    }

    public function supportsClass($class)
    {
        $supportedClass = IntegrationTagInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param IntegrationTagInterface $integrationTag
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($integrationTag, UserEntityInterface $user, $action)
    {
        if (!in_array($action, array(EntityVoterAbstract::VIEW, EntityVoterAbstract::EDIT))) {
            return false;
        }

        if ($action == EntityVoterAbstract::EDIT) return false;

        /** @var PublisherInterface $user */
        $integrationTag = $this->integrationTagManager->findByPublisher($user);
        if ($integrationTag instanceof IntegrationTagInterface) return true;

        return false;
    }
}