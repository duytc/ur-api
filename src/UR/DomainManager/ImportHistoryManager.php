<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Repository\Core\ImportHistoryRepositoryInterface;
use UR\Worker\Manager;

class ImportHistoryManager implements ImportHistoryManagerInterface
{
    protected $om;
    protected $repository;
    protected $workerManager;

    public function __construct(ObjectManager $om, ImportHistoryRepositoryInterface $repository, Manager $workerManager)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->workerManager = $workerManager;
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

    /**
     * @inheritdoc
     */
    public function getImportedHistoryByDataSet(DataSetInterface $dataSet)
    {
        return $this->repository->getImportedHistoryByDataSet($dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getImportedHistoryByDataSetQuery(DataSetInterface $dataSet, PagerParam $param)
    {
        return $this->repository->getImportedHistoryByDataSetQuery($dataSet, $param);
    }

    /**
     * @inheritdoc
     */
    public function getImportedHistoryByDataSourceQuery(DataSourceInterface $dataSource, PagerParam $param)
    {
        return $this->repository->getImportedHistoryByDataSourceQuery($dataSource, $param);
    }

    /**
     * @inheritdoc
     */
    public function getImportHistoryByDataSourceEntry(DataSourceEntryInterface $dataSourceEntry, DataSetInterface $dataSet, ImportHistoryInterface $importHistory)
    {
        return $this->repository->getImportHistoryByDataSourceEntry($dataSourceEntry, $dataSet, $importHistory);
    }

    /**
     * @inheritdoc
     */
    public function getImportHistoryByDataSourceEntryWithoutDataSet(DataSourceEntryInterface $dataSourceEntry)
    {
        return $this->repository->getImportHistoryByDataSourceEntryWithoutDataSet($dataSourceEntry);
    }

    /**
     * @inheritdoc
     */
    public function deletePreviousImports($importHistories, ConnectedDataSourceInterface $connectedDataSource)
    {
        $this->repository->deletePreviousImports($importHistories, $connectedDataSource);
    }

    /**
     * @inheritdoc
     */
    public function createImportHistoryByDataSourceEntryAndConnectedDataSource(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource)
    {
        $importHistoryEntity = new ImportHistory();
        $importHistoryEntity->setDataSourceEntry($dataSourceEntry);
        $importHistoryEntity->setDataSet($connectedDataSource->getDataSet());
        $this->save($importHistoryEntity);
        return $importHistoryEntity;
    }

    public function deleteImportHistoryByDataSet(DataSetInterface $dataSet)
    {
        return $this->repository->deleteImportHistoryByDataSet($dataSet);
    }

    /**
     * @param ImportHistoryInterface[] $importHistories
     * @return mixed
     */
    public function deleteImportedData($importHistories)
    {
        return $this->repository->deleteImportedData($importHistories);
    }
}