<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\ModelInterface;
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
}