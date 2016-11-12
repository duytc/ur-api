<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use ReflectionClass;

class DataSetManager implements DataSetManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSetRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function getDataSetForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->repository->getDataSetsForPublisher($publisher, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSetInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSetInterface) throw new InvalidArgumentException('expect DataSetInterface Object');
        $this->om->persist($dataSource);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSetInterface) throw new InvalidArgumentException('expect DataSetInterface Object');
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

    /**
     * @inheritdoc
     */
    public function getDataSetByDataSource(DataSourceInterface $dataSource)
    {
        return $this->repository->getDataSetByDataSource($dataSource);
    }
}