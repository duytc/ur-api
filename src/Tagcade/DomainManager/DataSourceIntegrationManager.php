<?php

namespace Tagcade\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Tagcade\Exception\InvalidArgumentException;
use Tagcade\Model\Core\DataSourceIntegrationInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Repository\Core\DataSourceIntegrationRepositoryInterface;
use ReflectionClass;

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
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }
}