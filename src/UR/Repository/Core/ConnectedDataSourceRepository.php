<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;

class ConnectedDataSourceRepository extends EntityRepository implements ConnectedDataSourceRepositoryInterface
{
    const DATA_ADDED = 'dataAdded';
    const IMPORT_FAILURE= 'importFailure';
    public static $alertSetting = [ConnectedDataSourceRepository::DATA_ADDED, ConnectedDataSourceRepository::IMPORT_FAILURE];

    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    public function getConnectedDataSourceByDataSetQuery(DataSetInterface $dataSet, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('cds')
            ->leftJoin('cds.dataSource' ,'ds')
            ->select('cds, ds')
            ->where('cds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('cds.id', ':searchKey'),
                    $qb->expr()->like('ds.name', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('cds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('cds.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

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