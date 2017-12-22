<?php


namespace UR\Repository\Core;


use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;

class AutoOptimizationConfigDataSetRepository extends EntityRepository implements AutoOptimizationConfigDataSetRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findByDataSet(DataSetInterface $dataSet)
    {
        return $this->createQueryBuilder('aocds')
            ->where('aocds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->getQuery()
            ->getResult();
    }
}