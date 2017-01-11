<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
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
}