<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\TagRepositoryInterface;

class TagManager implements TagManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, TagRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, TagInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $tag)
    {
        if (!$tag instanceof TagInterface) throw new InvalidArgumentException('expect TagInterface object');

        $this->om->persist($tag);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $tag)
    {
        if (!$tag instanceof TagInterface) throw new InvalidArgumentException('expect TagInterface object');

        $this->om->remove($tag);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->repository->getClassName());
        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        return $this->repository->findByPublisher($publisher);
    }

    /**
     * @inheritdoc
     */
    public function findByIntegration(IntegrationInterface $integration)
    {
        return $this->repository->findByIntegration($integration);
    }

    /**
     * @inheritdoc
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplateInterface)
    {
        return $this->repository->findByReportViewTemplate($reportViewTemplateInterface);
    }

    /**
     * @inheritdoc
     */
    public function findByName($tagName)
    {
        return $this->repository->findByName($tagName);
    }

    public function checkIfUserHasMatchingIntegrationTag(IntegrationInterface $integration, PublisherInterface $publisher)
    {
        return $this->repository->checkIfUserHasMatchingIntegrationTag($integration, $publisher);
    }
}