<?php


namespace UR\Service\DataSource;


use UR\DomainManager\TagManagerInterface;
use UR\DomainManager\UserTagManagerInterface;
use UR\Entity\Core\IntegrationTag;
use UR\Entity\Core\Tag;
use UR\Entity\Core\UserTag;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

class IntegrationTagService implements IntegrationTagServiceInterface
{
    /**
     * @var TagManagerInterface
     */
    protected $tagManager;

    /**
     * @var UserTagManagerInterface
     */
    protected $userTagManager;

    /**
     * IntegrationTagService constructor.
     * @param $tagManager
     * @param $userTagManager
     */
    public function __construct(TagManagerInterface $tagManager, UserTagManagerInterface $userTagManager)
    {
        $this->tagManager = $tagManager;
        $this->userTagManager = $userTagManager;
    }

    /**
     * @param IntegrationInterface $integration
     * @param PublisherInterface $publisher
     * @return IntegrationInterface
     */
    public function createIntegrationTagForUser(IntegrationInterface $integration, PublisherInterface $publisher)
    {

        //create user tag
        $tagName = $this->getUniqueTagNameForIntegration($integration);
        $tag = new Tag();
        $tag->setName($tagName);
        $this->tagManager->save($tag);

        $integrationTag = new IntegrationTag();
        $integrationTag->setTag($tag);
        $integrationTag->setIntegration($integration);
        $integration->setIntegrationTags([$integrationTag]);

        $userTag = new UserTag();
        $userTag->setPublisher($publisher)->setTag($tag);
        $this->userTagManager->save($userTag);

        return $integration;
    }

    public function updateIntegrationTagForUser(IntegrationInterface $integration, PublisherInterface $publisher)
    {
        $tag = $this->tagManager->checkIfUserHasMatchingIntegrationTag($integration, $publisher);
        if ($tag instanceof TagInterface) {
            return $integration;
        }

        return $this->createIntegrationTagForUser($integration, $publisher);
    }

    protected function getUniqueTagNameForIntegration(IntegrationInterface $integration)
    {
        $tried = 0;
        $tagName = '';
        while (true) {
            if ($tried == 0) {
                $tagName = sprintf('integration-%s', $integration->getCanonicalName());
            } else {
                $tagName = sprintf('integration-%s-%d', $integration->getCanonicalName(), $tried);
            }

            $tag = $this->tagManager->findByName($tagName);
            if (!$tag instanceof TagInterface) {
                break;
            }
            $tried++;
        }

        return $tagName;
    }
}