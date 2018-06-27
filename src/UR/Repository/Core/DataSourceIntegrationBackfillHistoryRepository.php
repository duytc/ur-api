<?php

namespace UR\Repository\Core;

use DateInterval;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class DataSourceIntegrationBackfillHistoryRepository extends EntityRepository implements DataSourceIntegrationBackfillHistoryRepositoryInterface
{

    /**
     * @param integer $dataSourceIntegrationId
     * @return array|DataSourceIntegrationInterface[]
     */
    public function findByDataSourceIntegration($dataSourceIntegrationId)
    {
        $qb = $this->createQueryBuilder('dibh')
            ->where('dibh.dataSourceIntegration = :dataSourceIntegrationId')
            ->setParameter('dataSourceIntegrationId', $dataSourceIntegrationId)
            ->distinct();

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getBackfillHistoriesByDataSourceId(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->where('di.dataSource = :dataSource')
            ->orderBy('dibh.queuedAt', 'desc')
            ->setParameter('dataSource', $dataSource);

        $result = $qb->getQuery()->getResult();

        $nullExecutedBackFillHistories = array_filter($result, function ($backFill) {
            /* @var DataSourceIntegrationBackfillHistoryInterface $backFill */
            return $backFill->getQueuedAt() == null;
        });

        $notNullExecutedBackFillHistories = array_filter($result, function ($backFill) {
            /* @var DataSourceIntegrationBackfillHistoryInterface $backFill */
            return $backFill->getQueuedAt() != null;
        });

        return array_merge($nullExecutedBackFillHistories, $notNullExecutedBackFillHistories);
    }

    /**
     * @inheritdoc
     */
    public function findByBackFillNotExecuted()
    {
        $currentDate = new \DateTime();
        $currentDate->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));
        $yesterday = new \DateTime();
        $yesterday = $yesterday->sub(new DateInterval('P1D'))->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));

        // get all schedule have status = 1, nextExecuted < currentDate, queueAt < yesterday
        //Ensure an unexpected exception will occur such as shutdown redis before running the fetcher worker

        // handle query nested -- jennyphuong
        $qb = $this->createQueryBuilder('dibh');
        $qb
            ->where(
                $qb->expr()->in('dibh.id',
                    $this->getBackfillQueryBefore($yesterday->format('Y-m-d H:i:s'))->select('dibh1.id')->getDQL()))
            ->orWhere(
                $qb->expr()->in('dibh.id',
                    $this->getBackfillQueryAfter()->select('dibh2.id')->getDQL()));

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function findByBackFillNotExecutedForDataSource($dataSourceIntegrationId)
    {
        $currentDate = new \DateTime();
        $currentDate->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));
        $yesterday = new \DateTime();
        $yesterday = $yesterday->sub(new DateInterval('P1D'))->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));

        // get all schedule have status = 1, nextExecuted < currentDate, queueAt < yesterday
        //Ensure an unexpected exception will occur such as shutdown redis before running the fetcher worker

        // handle query nested -- jennyphuong
        $qb = $this->createQueryBuilder('dibh');
        $qb
            ->where(
                $qb->expr()->in('dibh.id',
                    $this->getBackfillQueryBeforeForDataSource($dataSourceIntegrationId, $yesterday->format('Y-m-d H:i:s'))->select('dibh1.id')->getDQL()))
            ->orWhere(
                $qb->expr()->in('dibh.id',
                    $this->getBackfillQueryAfterForDataSource($dataSourceIntegrationId)->select('dibh2.id')->getDQL()));

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function findHistoryByStartDateEndDate($startDate, $endDate, $dataSourceIntegrationId)
    {
        $qb = $this->createQueryBuilder('dibh')
            ->where('dibh.dataSourceIntegration = :dataSourceIntegrationId')
            ->andWhere('dibh.backFillStartDate = :startDate')
            ->setParameter('dataSourceIntegrationId', $dataSourceIntegrationId)
            ->setParameter('startDate', $startDate);

        if ($endDate instanceof \DateTime) {
            $qb->andWhere('dibh.backFillEndDate = :endDate')
                ->setParameter('endDate', $endDate);
        } else {
            $qb->andWhere('dibh.backFillEndDate is null');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getBackfillHistoriesByDataSourceIdWithAutoCreated(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->where('di.dataSource = :dataSource')
            ->andWhere('dibh.autoCreate = :autoCreate')
            ->setParameter('dataSource', $dataSource)
            ->setParameter('autoCreate', true);

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function getCurrentAutoCreateBackFillHistory(DataSourceInterface $dataSource)
    {
        $qb = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->where('di.dataSource = :dataSource')
            ->andWhere('dibh.queuedAt is null')
            ->andWhere('dibh.finishedAt is null')
            ->andWhere('dibh.autoCreate = true')
            ->setParameter('dataSource', $dataSource);

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @param null $queueAt
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getBackfillQueryBefore($queueAt = null)
    {
        $queueAt = "'" . $queueAt . "'";
        $qb = $this->createQueryBuilder('dibh1')
            ->join('dibh1.dataSourceIntegration', 'di1')
            ->join('di1.dataSource', 'ds1')
            ->andWhere('ds1.enable = true')
            ->andWhere('dibh1.status = ' . DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING)
            ->andWhere('dibh1.finishedAt is null');

        if ($queueAt != null) {
            $qb->andWhere('dibh1.queuedAt <= ' . $queueAt);
        } else {
            $qb->andWhere('dibh.queuedAt is null');
        }

        return $qb;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getBackfillQueryAfter()
    {
        $qb = $this->createQueryBuilder('dibh2')
            ->join('dibh2.dataSourceIntegration', 'di2')
            ->join('di2.dataSource', 'ds2')
            ->andWhere('ds2.enable = true')
            ->andWhere('dibh2.status = ' . DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN)
            ->andWhere('dibh2.finishedAt is null')
            ->andWhere('dibh2.queuedAt is null');

        return $qb;
    }

    /**
     * @param $dataSourceIntegrationId
     * @param null $queueAt
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getBackfillQueryBeforeForDataSource($dataSourceIntegrationId, $queueAt = null)
    {
        $queueAt = "'" . $queueAt . "'";
        $qb = $this->createQueryBuilder('dibh1')
            ->join('dibh1.dataSourceIntegration', 'di1')
            ->join('di1.dataSource', 'ds1')
            ->andWhere('ds1.enable = true')
            ->andWhere('dibh1.status = ' . DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING)
            ->andWhere('dibh1.finishedAt is null')
            ->andWhere('dibh1.dataSourceIntegration = '. $dataSourceIntegrationId);

        if ($queueAt != null) {
            $qb->andWhere('dibh1.queuedAt <= ' . $queueAt);
        } else {
            $qb->andWhere('dibh.queuedAt is null');
        }

        return $qb;
    }

    /**
     * @param $dataSourceIntegrationId
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getBackfillQueryAfterForDataSource($dataSourceIntegrationId)
    {
        $qb = $this->createQueryBuilder('dibh2')
            ->join('dibh2.dataSourceIntegration', 'di2')
            ->join('di2.dataSource', 'ds2')
            ->andWhere('ds2.enable = true')
            ->andWhere('dibh2.status = ' . DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN)
            ->andWhere('dibh2.finishedAt is null')
            ->andWhere('dibh2.queuedAt is null')
            ->andWhere('dibh2.dataSourceIntegration = '. $dataSourceIntegrationId);

        return $qb;
    }

}