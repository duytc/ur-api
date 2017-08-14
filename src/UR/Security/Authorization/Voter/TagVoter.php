<?php

namespace UR\Security\Authorization\Voter;

use UR\DomainManager\TagManagerInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class TagVoter extends EntityVoterAbstract
{
    /** @var TagManagerInterface  */
    private $tagManager;

    /**
     * TagVoter constructor.
     * @param TagManagerInterface $tagManager
     */
    public function __construct(TagManagerInterface $tagManager)
    {
        $this->tagManager = $tagManager;
    }

    public function supportsClass($class)
    {
        $supportedClass = TagInterface::class;

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param TagInterface $tag
     * @param UserEntityInterface $user
     * @param $action
     * @return bool
     */
    protected function isPublisherActionAllowed($tag, UserEntityInterface $user, $action)
    {
        if (!in_array($action, array(EntityVoterAbstract::VIEW, EntityVoterAbstract::EDIT))) {
            return false;
        }

        if ($action == EntityVoterAbstract::EDIT) return false;

        /** @var PublisherInterface $user*/
        $tags = $this->tagManager->findByPublisher($user);
        if (is_array($tags) && !empty($tags)) return true;

        return false;
    }
}