<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Repository\Core\ConnectedDataSourceRepositoryInterface;

    class ConnectedDataSourceManager implements ConnectedDataSourceManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ConnectedDataSourceRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ConnectedDataSourceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $connectedDataSource)
    {
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) throw new InvalidArgumentException('expect ConnectedDataSourceInterface object');

        $this->om->persist($connectedDataSource);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $connectedDataSource)
    {
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) throw new InvalidArgumentException('expect ConnectedDataSourceInterface object');

        $this->om->remove($connectedDataSource);
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
    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet)
    {
        return $this->repository->getConnectedDataSourceByDataSet($dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSourceByDataSource(DataSourceInterface $dataSource)
    {
        return $this->repository->getConnectedDataSourceByDataSource($dataSource);
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSourceByDataSetQuery(DataSetInterface $dataSet, PagerParam $params)
    {
        return $this->repository->getConnectedDataSourceByDataSetQuery($dataSet, $params);
    }
}