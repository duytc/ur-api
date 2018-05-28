<?php

namespace UR\Repository\Core;

use DateInterval;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class DataSourceIntegrationScheduleRepository extends EntityRepository implements DataSourceIntegrationScheduleRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findToBeExecuted()
    {
        $currentDate = new \DateTime();
        $currentDate->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));
        $yesterday = new \DateTime();
        $yesterday = $yesterday->sub(new DateInterval('P1D'))->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));

        // get all schedule have status = 1, nextExecuted < currentDate, queueAt < yesterday
        //Ensure an unexpected exception will occur such as shutdown redis before running the fetcher worker
        $qbStatusIsPending = $this->createQueryBuilder('dis');
        $qbStatusIsPending
            ->join('dis.dataSourceIntegration', 'dsi')
            ->join('dsi.dataSource', 'ds')
            ->andWhere('dis.nextExecutedAt <= :currentDate')
            ->andWhere('dis.queuedAt <= :yesterday')
            ->andWhere('dsi.active = :dataSourceActive')
            ->andWhere('dis.status = :status')
            ->andWhere('ds.enable = :enable')
            ->andWhere('dis.finishedAt is null')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('yesterday', $yesterday)
            ->setParameter('dataSourceActive', true)
            ->setParameter('enable', true)
            ->setParameter('status', DataSourceIntegrationScheduleInterface::FETCHER_STATUS_PENDING);

        $schedulesStatusIsPending = $qbStatusIsPending->getQuery()->getResult();

        // normal tobeExecutedSchedule
        $qb = $this->createQueryBuilder('dis');
        $qb
            ->join('dis.dataSourceIntegration', 'dsi')
            ->join('dsi.dataSource', 'ds')
            ->andWhere('dis.nextExecutedAt <= :currentDate')
            ->andWhere('dsi.active = :dataSourceActive')
            ->andWhere('dis.status = :status')
            ->andWhere('ds.enable = :enable')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('dataSourceActive', true)
            ->setParameter('enable', true)
            ->setParameter('status', DataSourceIntegrationScheduleInterface::FETCHER_STATUS_NOT_RUN);

        $schedules = $qb->getQuery()->getResult();

        $schedules = array_merge($schedules, $schedulesStatusIsPending);

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

    /**
     * @param IntegrationInterface $integration
     * @return array|\UR\Model\Core\DataSourceIntegrationScheduleInterface[]
     */
    public function findByIntegration(IntegrationInterface $integration)
    {
        $qb = $this->createQueryBuilder('dis')
            ->join('dis.dataSourceIntegration', 'dsi')
            ->where('dsi.integration = :integration')
            ->setParameter('integration', $integration);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByUUID($uuid)
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.uuid = :uuid')
            ->setParameter('uuid', $uuid);

        return $qb->getQuery()->getOneOrNullResult();
    }
}