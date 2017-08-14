<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\ReportViewTemplateTagRepositoryInterface;

class ReportViewTemplateTagManager implements ReportViewTemplateTagManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ReportViewTemplateTagRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ReportViewTemplateTagInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $reportViewTemplateTag)
    {
        if (!$reportViewTemplateTag instanceof ReportViewTemplateTagInterface) throw new InvalidArgumentException('expect ReportViewTemplateTagInterface object');

        $this->om->persist($reportViewTemplateTag);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $reportViewTemplateTag)
    {
        if (!$reportViewTemplateTag instanceof ReportViewTemplateTagInterface) throw new InvalidArgumentException('expect ReportViewTemplateTagInterface object');

        $this->om->remove($reportViewTemplateTag);
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
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplate)
    {
        return $this->repository->findByReportViewTemplate($reportViewTemplate);
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        return $this->repository->findByTag($tag);
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
    public function findByReportViewTemplateAndTag(ReportViewTemplateInterface $reportViewTemplate, TagInterface $tag)
    {
        return $this->repository->findByReportViewTemplateAndTag($reportViewTemplate, $tag);
    }
}