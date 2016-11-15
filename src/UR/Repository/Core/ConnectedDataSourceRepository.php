<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;

class ConnectedDataSourceRepository extends EntityRepository implements ConnectedDataSourceRepositoryInterface
{
    const DATA_ADDED = 'dataAdded';
    const IMPORT_FAILURE= 'importFailure';

    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet)
    {
        $qb = $this->createQueryBuilder('cds')
            ->leftJoin('cds.dataSource' ,'ds')
            ->select('cds, ds')
            ->where('cds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet);

        return $qb->getQuery()->getResult();
    }

    public function getConnectedDataSourceByDataSource(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('cds')
            ->leftJoin('cds.dataSource' ,'ds')
            ->select('cds, ds')
            ->where('cds.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource);

        return $qb->getQuery()->getResult();
    }
}