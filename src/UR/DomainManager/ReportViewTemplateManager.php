<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\ReportViewTemplateRepositoryInterface;

class ReportViewTemplateManager implements ReportViewTemplateManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ReportViewTemplateRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ReportViewTemplateInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $reportViewTemplate)
    {
        if (!$reportViewTemplate instanceof ReportViewTemplateInterface) throw new InvalidArgumentException('expect ReportViewTemplateInterface object');

        $this->om->persist($reportViewTemplate);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $reportViewTemplate)
    {
        if (!$reportViewTemplate instanceof ReportViewTemplateInterface) throw new InvalidArgumentException('expect ReportViewTemplateInterface object');

        $this->om->remove($reportViewTemplate);
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
    public function findByTag(TagInterface $tag)
    {
        return $this->repository->findByTag($tag);
    }

    /**
     * @param PublisherInterface $publisher
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        return $this->repository->findByPublisher($publisher);
    }
}