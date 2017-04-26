<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Entity\Core\DataSetImportJob;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\DataSetImportJobRepositoryInterface;

class DataSetImportJobManager implements DataSetImportJobManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSetImportJobRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSetImportJobInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSetImportJob)
    {
        if (!$dataSetImportJob instanceof DataSetImportJobInterface) throw new InvalidArgumentException('expect DataSetImportJobInterface Object');
        $this->om->persist($dataSetImportJob);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSetImportJobInterface) throw new InvalidArgumentException('expect DataSetImportJobInterface Object');
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
    public function getExecuteImportJobByDataSetId($dataSetId)
    {
        return $this->repository->getExecuteImportJobByDataSetId($dataSetId);
    }

    /**
     * @inheritdoc
     */
    public function getExecuteImportJobByJobId($jobId)
    {
        return $this->repository->getExecuteImportJobByJobId($jobId);
    }

    /**
     * @inheritdoc
     */
    public function createNewDataSetImportJob(DataSetInterface $dataSet, $jobName, array $jobData)
    {
        $dataSetImportJob = DataSetImportJob::createEmptyDataSetImportJob($dataSet, $jobName, DataSetImportJob::JOB_TYPE_IMPORT, $jobData);

        $this->save($dataSetImportJob);

        return $dataSetImportJob;
    }
}