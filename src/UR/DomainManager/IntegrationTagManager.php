<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\IntegrationTagInterface;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\IntegrationTagRepositoryInterface;

class IntegrationTagManager implements IntegrationTagManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, IntegrationTagRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, IntegrationTagInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $integrationTag)
    {
        if (!$integrationTag instanceof IntegrationTagInterface) throw new InvalidArgumentException('expect IntegrationTagInterface object');

        $this->om->persist($integrationTag);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $integrationTag)
    {
        if (!$integrationTag instanceof IntegrationTagInterface) throw new InvalidArgumentException('expect IntegrationTagInterface object');

        $this->om->remove($integrationTag);
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
    public function findByIntegration(IntegrationInterface $integration) {
        return $this->repository->findByIntegration($integration);
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag) {
        return $this->repository->findByTag($tag);
    }

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher) {
        return $this->repository->findByPublisher($publisher);
    }
}