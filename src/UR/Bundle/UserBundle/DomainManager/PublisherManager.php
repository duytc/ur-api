<?php

namespace UR\Bundle\UserBundle\DomainManager;

use Doctrine\Common\Collections\Collection;
use FOS\UserBundle\Model\UserInterface as FOSUserInterface;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Ramsey\Uuid\Uuid;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\DomainManager\UserTagManagerInterface;
use UR\Exception\LogicException;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\UserTagInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

/**
 * Most of the other handlers talk to doctrine directly
 * This one is wrapping the bundle-specific FOSUserBundle
 * whilst keep a consistent API with the other handlers
 */
class PublisherManager implements PublisherManagerInterface
{
    const ROLE_PUBLISHER = 'ROLE_PUBLISHER';
    const ROLE_ADMIN = 'ROLE_ADMIN';

    /**
     * @var UserManagerInterface
     */
    protected $FOSUserManager;

    /** @var UserTagManagerInterface  */
    protected $userTagManager;

    public function __construct(UserManagerInterface $userManager, UserTagManagerInterface $userTagManager)
    {
        $this->FOSUserManager = $userManager;
        $this->userTagManager = $userTagManager;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, FOSUserInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(FOSUserInterface $user)
    {
        $this->FOSUserManager->updateUser($user);
    }

    /**
     * @inheritdoc
     */
    public function delete(FOSUserInterface $user)
    {
        $this->FOSUserManager->deleteUser($user);
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        return $this->FOSUserManager->createUser();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->FOSUserManager->findUserBy(['id' => $id]);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->FOSUserManager->findUsers();
    }

    /**
     * @inheritdoc
     */
    public function allPublishers()
    {
        $publishers = array_filter($this->all(), function(UserEntityInterface $user) {
            return $user->hasRole(static::ROLE_PUBLISHER);
        });

        return array_values($publishers);
    }

    /**
     * @return array
     */
    public function allActivePublishers()
    {
        $publishers = array_filter($this->all(), function(UserEntityInterface $user) {
            return $user->hasRole(static::ROLE_PUBLISHER) && $user->isEnabled();
        });

        return array_values($publishers);
    }

    /**
     * @inheritdoc
     */
    public function findPublisher($id)
    {
        $publisher = $this->find($id);

        if (!$publisher) {
            return false;
        }

        if (!$publisher instanceof PublisherInterface) {
            return false;
        }

        return $publisher;
    }

    /**
     * @inheritdoc
     */
    public function findUserByUsernameOrEmail($usernameOrEmail)
    {
        return $this->FOSUserManager->findUserByUsernameOrEmail($usernameOrEmail);
    }

    /**
     * @inheritdoc
     */
    public function updateUser(UserInterface $token)
    {
        $this->FOSUserManager->updateUser($token);
    }

    /**
     * @inheritdoc
     */
    public function findUserByConfirmationToken($token)
    {
        return $this->FOSUserManager->findUserByConfirmationToken($token);
    }

    public function updateCanonicalFields(UserInterface $user)
    {
        $this->FOSUserManager->updateCanonicalFields($user);
    }

    public function generateUuid(UserInterface $user)
    {
        try {
            $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, $user->getEmail());
            return $uuid5->toString();

        } catch(UnsatisfiedDependencyException $e) {
            throw new LogicException($e->getMessage());
        }
    }

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @return PublisherInterface[]
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplate) {
        $reportViewTemplateTags = $reportViewTemplate->getReportViewTemplateTags();

        if ($reportViewTemplateTags instanceof Collection) {
            $reportViewTemplateTags = $reportViewTemplateTags->toArray();
        }

        $publishers = [];
        $publisherIds = [];
        foreach ($reportViewTemplateTags as $reportViewTemplateTag) {
            if (!$reportViewTemplateTag instanceof ReportViewTemplateTagInterface) {
                continue;
            }

            $tag = $reportViewTemplateTag->getTag();
            $userTags = $this->userTagManager->findByTag($tag);

            foreach ($userTags as $userTag) {
                if (!$userTag instanceof UserTagInterface) {
                    continue;
                }

                $publisher = $userTag->getPublisher();

                if (!in_array(User::MODULE_UNIFIED_REPORT,$publisher->getUser()->getEnabledModules())) {
                    continue;
                }

                if (!in_array($userTag->getPublisher()->getId(), $publisherIds)) {
                    $publishers[] = $userTag->getPublisher();
                    $publisherIds[] = $userTag->getPublisher()->getId();
                }
            }
        }

        return $publishers;
    }
}