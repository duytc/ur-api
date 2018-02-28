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
        $qbStatusIsPending = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->join('di.dataSource', 'ds')
            ->andWhere('ds.enable = true')
            ->andWhere('dibh.queuedAt <= :yesterday')
            ->andWhere('dibh.status = :status')
            ->andWhere('dibh.finishedAt is null')
            ->setParameter('yesterday', $yesterday)
            ->setParameter('status', DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING);

        $backFillsStatusIsPending = $qbStatusIsPending->getQuery()->getResult();

        $qb = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->join('di.dataSource', 'ds')
            ->andWhere('ds.enable = true')
            ->andWhere('dibh.status = :status')
            ->andWhere('dibh.queuedAt is null')
            ->andWhere('dibh.finishedAt is null')
            ->setParameter('status', DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN);

        $backFills = $qb->getQuery()->getResult();

        return array_merge($backFills, $backFillsStatusIsPending);
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
}