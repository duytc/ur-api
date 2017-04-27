<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;

class ReportViewMultiViewRepository extends EntityRepository implements ReportViewMultiViewRepositoryInterface
{
    public function getByReportView(ReportViewInterface $reportView)
    {
        return $this->createQueryBuilder('rv')
            ->where('rv.reportView = :reportView')
            ->setParameter('reportView', $reportView)
            ->getQuery()
            ->getResult();
    }

	public function getBySubView(ReportViewInterface $subView)
	{
		return $this->createQueryBuilder('rv')
			->where('rv.subView = :subView')
			->setParameter('subView', $subView)
			->getQuery()
			->getResult();
	}

    public function checkIfReportViewBelongsToMultiView(ReportViewInterface $reportView)
    {
        $result = $this->createQueryBuilder('rv')
            ->select('count(rv.id)')
            ->where('rv.subView = :reportView')
            ->setParameter('reportView', $reportView)
            ->getQuery()
            ->getSingleScalarResult();

        return intval($result) > 0;
    }
}