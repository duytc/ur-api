<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\IntegrationPublisherModelInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\IntegrationPublisherRepositoryInterface;

class IntegrationPublisherManager implements IntegrationPublisherManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, IntegrationPublisherRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, IntegrationPublisherModelInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $integration)
    {
        if (!$integration instanceof IntegrationPublisherModelInterface) throw new InvalidArgumentException('expect IntegrationPublisherModelInterface object');

        $this->om->persist($integration);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $integration)
    {
        if (!$integration instanceof IntegrationPublisherModelInterface) throw new InvalidArgumentException('expect IntegrationPublisherModelInterface object');

        $this->om->remove($integration);
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
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function findByIntegration(IntegrationInterface $integration)
    {
        return $this->repository->getByIntegration($integration);
    }

    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        // make sure canonical name is unique to return correct result!
        return $this->repository->getByPublisher($publisher);
    }
}