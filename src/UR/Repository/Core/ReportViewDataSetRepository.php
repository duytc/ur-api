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
}