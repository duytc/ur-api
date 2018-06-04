<?php

namespace UR\Repository\Core;

use DateInterval;
use Doctrine\ORM\EntityRepository;
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

        // handle query nested -- jennyphuong
        $qb = $this->createQueryBuilder('dis');
        $qb
            ->where(
                $qb->expr()->in('dis.id',
                    $this->getDataSourceIntegrationScheduleQueryBefore($currentDate->format('Y-m-d H:i:s'), $yesterday->format('Y-m-d H:i:s'))->select('dis1.id')->getDQL()))
            ->orWhere(
                $qb->expr()->in('dis.id',
                    $this->getDataSourceIntegrationScheduleQueryAfter($currentDate->format('Y-m-d H:i:s'))->select('dis2.id')->getDQL()));

        return $qb;
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

    /**
     * @param null $currentDate
     * @param null $yesterday
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getDataSourceIntegrationScheduleQueryBefore($currentDate = null, $yesterday = null)
    {
        /*
         * SELECT c0_.id as id FROM core_data_source_integration_schedule c0_
                INNER JOIN core_data_source_integration c1_ ON c0_.data_source_integration_id = c1_.id
                INNER JOIN core_data_source c2_ ON c1_.data_source_id = c2_.id
                WHERE c0_.next_executed_at <= "2018-06-02 01:49:48"
                AND c0_.queued_at <= "2018-06-01 01:49:48"
                AND c1_.active = TRUE
                AND c0_.status = 0
                AND c2_.enable = 1
                AND c0_.finished_at IS NULL
                //WHERE c0_.next_executed_at <= "%s" AND c0_.queued_at <= "%s" AND c1_.active = %s AND c0_.status = %d AND c2_.enable = %s AND c0_.finished_at IS NULL
         */
        $currentDate = "'" . $currentDate . "'";
        $yesterday = "'" . $yesterday . "'";
        $qb = $this->createQueryBuilder('dis1')
            ->join('dis1.dataSourceIntegration', 'dsi1')
            ->join('dsi1.dataSource', 'ds1')
            ->andWhere('dis1.nextExecutedAt <= ' . $currentDate)
            ->andWhere('dis1.queuedAt <= ' . $yesterday)
            ->andWhere('dsi1.active = true')
            ->andWhere('dis1.status = ' . DataSourceIntegrationScheduleInterface::FETCHER_STATUS_PENDING)
            ->andWhere('ds1.enable = 1')
            ->andWhere('dis1.finishedAt is null');

        return $qb;
    }

    /**
     * @param null $currentDate
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getDataSourceIntegrationScheduleQueryAfter($currentDate = null)
    {
        /*
         * SELECT c0_.id AS id FROM core_data_source_integration_schedule c0_
                INNER JOIN core_data_source_integration c1_ ON c0_.data_source_integration_id = c1_.id
                INNER JOIN core_data_source c2_ ON c1_.data_source_id = c2_.id
                WHERE c0_.next_executed_at <= "2018-06-02 01:49:48"
                AND c1_.active = TRUE
                AND c0_.status = 0
                AND c2_.enable = 1
                //WHERE c0_.next_executed_at <= "%s" AND c1_.active = %s AND c0_.status = %d AND c2_.enable = %s
         */
        $currentDate = "'" . $currentDate . "'";
        $qb = $this->createQueryBuilder('dis2')
            ->join('dis2.dataSourceIntegration', 'dsi2')
            ->join('dsi2.dataSource', 'ds2')
            ->andWhere('dis2.nextExecutedAt <= ' . $currentDate)
            ->andWhere('dsi2.active = true')
            ->andWhere('dis2.status = ' . DataSourceIntegrationScheduleInterface::FETCHER_STATUS_NOT_RUN)
            ->andWhere('ds2.enable = 1');

        return $qb;
    }
}