<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\DataSourceIntegrationBackfillHistoryRepositoryInterface;

class DataSourceIntegrationBackfillHistoryManager implements DataSourceIntegrationBackfillHistoryManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceIntegrationBackfillHistoryRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceIntegrationBackfillHistoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSourceIntegrationBackfillHistory)
    {
        if (!$dataSourceIntegrationBackfillHistory instanceof DataSourceIntegrationBackfillHistoryInterface) throw new InvalidArgumentException('expect DataSourceIntegrationBackfillHistoryInterface Object');
        $this->om->persist($dataSourceIntegrationBackfillHistory);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSourceIntegrationBackfillHistory)
    {
        if (!$dataSourceIntegrationBackfillHistory instanceof DataSourceIntegrationBackfillHistoryInterface) throw new InvalidArgumentException('expect DataSourceIntegrationBackfillHistoryInterface Object');
        $this->om->remove($dataSourceIntegrationBackfillHistory);
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
    public function findByDataSourceIntegration($dataSourceIntegration)
    {
        return $this->repository->findByDataSourceIntegration($dataSourceIntegration);
    }

    /**
     * @inheritdoc
     */
    public function findByBackFillNotExecuted()
    {
        return $this->repository->findByBackFillNotExecuted();
    }

    /**
     * @inheritdoc
     */
    public function findHistoryByStartDateEndDate($startDate, $endDate, $dataSourceIntegration)
    {
        return $this->repository->findHistoryByStartDateEndDate($startDate, $endDate, $dataSourceIntegration);
    }
}