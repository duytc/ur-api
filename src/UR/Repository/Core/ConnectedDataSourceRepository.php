<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Entity\Core\ConnectedDataSource;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\PagerParam;

class ConnectedDataSourceRepository extends EntityRepository implements ConnectedDataSourceRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name', 'lastActivity' => 'lastActivity'];

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
                case $this->SORT_FIELDS['lastActivity']:
                    $qb->addOrderBy('ds.' . $param->getSortField(), $param->getSortDirection());
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

    public function updateTransforms(ConnectedDataSourceInterface $connectedDataSource, $transforms)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->update(ConnectedDataSource::class, 'cds')
            ->set('cds.transforms', $qb->expr()->literal(json_encode($transforms)))
            ->where('cds.id = ?1')
            ->setParameter(1, $connectedDataSource->getId())
            ->getQuery()->execute();
    }
}