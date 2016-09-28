<?php

namespace Tagcade\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use Tagcade\Exception\InvalidArgumentException;
use Tagcade\Model\Core\IntegrationInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Repository\Core\IntegrationRepositoryInterface;

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
    public function save(ModelInterface $adNetwork)
    {
        if (!$adNetwork instanceof IntegrationInterface) throw new InvalidArgumentException('expect IntegrationInterface object');

        $this->om->persist($adNetwork);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $adNetwork)
    {
        if (!$adNetwork instanceof IntegrationInterface) throw new InvalidArgumentException('expect IntegrationInterface object');

        $this->om->remove($adNetwork);
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
}