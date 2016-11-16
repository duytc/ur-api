<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\ImportHistoryRepositoryInterface;

class ImportHistoryManager implements ImportHistoryManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ImportHistoryRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ImportHistoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $importHistory)
    {
        if (!$importHistory instanceof ImportHistoryInterface) throw new InvalidArgumentException('expect ImportHistoryInterface object');

        $this->om->persist($importHistory);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $importHistory)
    {
        if (!$importHistory instanceof ImportHistoryInterface) throw new InvalidArgumentException('expect ImportHistoryInterface object');

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

    public function getImportedDataByDataSet(DataSetInterface $dataSet)
    {
        return $this->repository->getImportedDataByDataSet($dataSet);
    }
}