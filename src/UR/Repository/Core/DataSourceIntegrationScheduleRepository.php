<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

class DataSourceIntegrationScheduleRepository extends EntityRepository implements DataSourceIntegrationScheduleRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findToBeExecuted()
    {
        $currentDate = new \DateTime();

        $qb = $this->createQueryBuilder('dis');
        $qb
            ->join('dis.dataSourceIntegration', 'dsi')
            ->andWhere(
                $qb->expr()->orX(
                    // check by executeAt:
                    $qb->expr()->lte('dis.executedAt', ':currentDate'), // 'dis.executedAt <= :currentDate',
                    // check by backfill
                    $qb->expr()->andX(
                        $qb->expr()->eq('dsi.backFill', ':isBackfill'), // 'dsi.backFill = :isBackfill'
                        $qb->expr()->eq('dsi.backFillExecuted', ':isBackfillExecuted') // 'dsi.backFillExecuted = :isBackfillExecuted'
                    )
                )
            )
            ->andWhere('dsi.active = :dataSourceActive')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('isBackfill', true)
            ->setParameter('isBackfillExecuted', false)
            ->setParameter('dataSourceActive', true);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findByDataSource(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dis')
            ->join('dis.dataSourceIntegration', 'dsi')
            ->where('dsi.dataSource = :dataSource')
            ->setParameter('dataSource', $dataSource);

        return $qb->getQuery()->getResult();
    }
}