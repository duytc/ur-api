<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;

class ReportViewDataSetRepository extends EntityRepository implements ReportViewDataSetRepositoryInterface
{
    public function getByReportView(ReportViewInterface $reportView)
    {
        return $this->createQueryBuilder('rv')
            ->where('rv.reportView = :reportView')
            ->setParameter('reportView', $reportView)
            ->getQuery()
            ->getResult();
    }

    public function getByDataSet(DataSetInterface $dataSet)
    {
        return $this->createQueryBuilder('rv')
            ->where('rv.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->getQuery()
            ->getResult();
    }

    public function getDataSetsForReportViews(array $reportViewIds)
    {
        $qb = $this->createQueryBuilder('rvd');
        return $qb->join('rvd.reportView', 'rv')
            ->where($qb->expr()->in('rv.id', $reportViewIds))
            ->getQuery()
            ->getResult();
    }
}