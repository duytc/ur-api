<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\IntegrationRepositoryInterface;

class IntegrationManager implements IntegrationManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, IntegrationRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, IntegrationInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $integration)
    {
        if (!$integration instanceof IntegrationInterface) throw new InvalidArgumentException('expect IntegrationInterface object');

        $this->om->persist($integration);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $integration)
    {
        if (!$integration instanceof IntegrationInterface) throw new InvalidArgumentException('expect IntegrationInterface object');

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
     * @inheritdoc
     */
    public function findByName($name)
    {
        return $this->repository->findBy(
            array('name' => $name)
        );
    }

    /**
     * @inheritdoc
     */
    public function findByCanonicalName($canonicalName)
    {
        // make sure canonical name is unique to return correct result!
        return $this->repository->findOneBy(
            array('canonicalName' => $canonicalName)
        );
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        return $this->repository->findByTag($tag);
    }
}