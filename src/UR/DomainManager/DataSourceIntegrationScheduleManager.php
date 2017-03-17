<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\DataSourceIntegrationScheduleRepositoryInterface;

class DataSourceIntegrationScheduleManager implements DataSourceIntegrationScheduleManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceIntegrationScheduleRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceIntegrationScheduleInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSourceIntegrationSchedule)
    {
        if (!$dataSourceIntegrationSchedule instanceof DataSourceIntegrationScheduleInterface) throw new InvalidArgumentException('expect DataSourceIntegrationScheduleInterface Object');
        $this->om->persist($dataSourceIntegrationSchedule);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceIntegrationScheduleInterface) throw new InvalidArgumentException('expect DataSourceIntegrationScheduleInterface Object');
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
    public function findToBeExecuted()
    {
        return $this->repository->findToBeExecuted();
    }

    /**
     * @inheritdoc
     */
    public function updateExecuteAt(DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule, \DateTime $executedAt)
    {
        /**@var DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule */
        $dataSourceIntegrationSchedule->setExecutedAt($executedAt);
        $this->save($dataSourceIntegrationSchedule);
    }
}