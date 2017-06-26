<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationInterface;
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
            ->join('dsi.dataSource', 'ds')
            ->andWhere(
                $qb->expr()->orX(
                // check by executeAt:
                    $qb->expr()->lte('dis.executedAt', ':currentDate') // 'dis.executedAt <= :currentDate',
                )
            )
            ->andWhere('dsi.active = :dataSourceActive')
            ->andWhere('dis.pending = :pending')
            ->andWhere('ds.enable = :enable')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('dataSourceActive', true)
            ->setParameter('enable', true)
            ->setParameter('pending', false);

        $schedules = $qb->getQuery()->getResult();

        foreach ($schedules as $key => &$schedule) {
            if (!$schedule instanceof DataSourceIntegrationScheduleInterface) {
                continue;
            }
            $datSourceIntegration = $schedule->getDataSourceIntegration();

            if (!$datSourceIntegration instanceof DataSourceIntegrationInterface) {
                continue;
            }

            $schedule->setDataSourceIntegration($datSourceIntegration);
        }

        return $schedules;
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