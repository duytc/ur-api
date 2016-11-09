<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryImportHistoryInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\DataSourceEntryImportHistoryRepositoryInterface;

class DataSourceEntryImportHistoryManager implements DataSourceEntryImportHistoryManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceEntryImportHistoryRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceEntryImportHistoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $importHistory)
    {
        if (!$importHistory instanceof DataSourceEntryImportHistoryInterface) throw new InvalidArgumentException('expect DataSourceEntryImportHistoryInterface object');

        $this->om->persist($importHistory);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $importHistory)
    {
        if (!$importHistory instanceof DataSourceEntryImportHistoryInterface) throw new InvalidArgumentException('expect DataSourceEntryImportHistoryInterface object');

        $this->om->remove($importHistory);
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