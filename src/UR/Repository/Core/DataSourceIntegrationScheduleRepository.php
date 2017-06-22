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
            ->andWhere('dis.isRunning = :isRunning')
            ->andWhere('ds.enable = :enable')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('dataSourceActive', true)
            ->setParameter('enable', true)
            ->setParameter('isRunning', false);

        $schedules = $qb->getQuery()->getResult();

        foreach ($schedules as $key => &$schedule) {
            if (!$schedule instanceof DataSourceIntegrationScheduleInterface) {
                continue;
            }
            $datSourceIntegration = $schedule->getDataSourceIntegration();

            if (!$datSourceIntegration instanceof DataSourceIntegrationInterface) {
                continue;
            }

            $datSourceIntegration->setBackFill(false);
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