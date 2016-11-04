<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;

class ConnectedDataSourceRepository extends EntityRepository implements ConnectedDataSourceRepositoryInterface
{
    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet)
    {
        $qb = $this->createQueryBuilder('cds')
            ->where('cds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet);

        return $qb->getQuery()->getResult();
    }
}