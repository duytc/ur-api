<?php


namespace UR\Repository\Core;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;

class MapBuilderConfigRepository extends EntityRepository implements MapBuilderConfigRepositoryInterface
{
    public function getByDataSet(DataSetInterface $dataSet)
    {
        return $this->createQueryBuilder('mb')
            ->where('mb.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->getQuery()
            ->getResult();
    }

    public function getByMapDataSet(DataSetInterface $dataSet)
    {
        return $this->createQueryBuilder('mb')
            ->where('mb.mapDataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->getQuery()
            ->getResult();
    }
}