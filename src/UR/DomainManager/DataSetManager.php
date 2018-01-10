<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use UR\Entity\Core\AutoOptimizationConfigDataSet;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Entity\Core\ReportViewDataSet;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use ReflectionClass;
use UR\Service\PublicSimpleException;

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
    public function save(ModelInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface) {
            throw new InvalidArgumentException('expect DataSetInterface Object');
        }

        $this->om->persist($dataSet);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface) {
            throw new InvalidArgumentException('expect DataSetInterface Object');
        }

        $reportViewDataSetRepository = $this->om->getRepository(ReportViewDataSet::class);
        $reportViewDataSets = $reportViewDataSetRepository->getByDataSet($dataSet);
        if (count($reportViewDataSets) > 0) {
            throw new PublicSimpleException("There are some report views that refer to this data set");
        }

        $linkedMapDataSetRepository = $this->om->getRepository(LinkedMapDataSet::class);
        $linkedMaps = $linkedMapDataSetRepository->getByMapDataSet($dataSet);
        if (count($linkedMaps) > 0) {
            throw new PublicSimpleException("There are some connected data sources with data augmentation transforms that refer to this data set");
        }

        $autoOptimizationConfigDataSetRepository = $this->om->getRepository(AutoOptimizationConfigDataSet::class);
        $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSetRepository->findByDataSet($dataSet);

        if (count($autoOptimizationConfigDataSets) > 0) {
            throw new PublicSimpleException("There are some Auto Optimization that refer to this data set");
        }

        $this->om->remove($dataSet);
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
    public function getDataSetByDataSource(DataSourceInterface $dataSet)
    {
        return $this->repository->getDataSetByDataSource($dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getDataSetByName($dataSetName)
    {
        return $this->repository->getDataSetByName($dataSetName);
    }
}