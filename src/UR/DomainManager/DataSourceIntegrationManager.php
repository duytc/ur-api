<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\DataSourceIntegrationRepositoryInterface;

class DataSourceIntegrationManager implements DataSourceIntegrationManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceIntegrationRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceIntegrationInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceIntegrationInterface) throw new InvalidArgumentException('expect DataSourceIntegrationInterface Object');
        $this->om->persist($dataSource);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceIntegrationInterface) throw new InvalidArgumentException('expect DataSourceIntegrationInterface Object');
        $this->om->remove($dataSource);
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
    public function findByIntegrationCanonicalName($canonicalName)
    {
        return $this->repository->findByIntegrationCanonicalName($canonicalName);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }
}