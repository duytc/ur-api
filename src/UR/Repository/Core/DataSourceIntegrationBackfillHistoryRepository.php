<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;

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
            ->orderBy('dibh.executedAt', 'desc')
            ->setParameter('dataSource', $dataSource);

        $result = $qb->getQuery()->getResult();

        $nullExecutedBackFillHistories = array_filter($result, function ($backFill) {
            /* @var DataSourceIntegrationBackfillHistoryInterface $backFill */
            return $backFill->getExecutedAt() == null;
        });

        $notNullExecutedBackFillHistories = array_filter($result, function ($backFill) {
            /* @var DataSourceIntegrationBackfillHistoryInterface $backFill */
            return $backFill->getExecutedAt() != null;
        });

        return array_merge($nullExecutedBackFillHistories, $notNullExecutedBackFillHistories);
    }

    /**
     * @inheritdoc
     */
    public function findByBackFillNotExecuted()
    {
        $qb = $this->createQueryBuilder('dibh')
            ->join('dibh.dataSourceIntegration', 'di')
            ->join('di.dataSource', 'ds')
            ->where('dibh.executedAt is null')
            ->andWhere('ds.enable = true')
            ->andWhere('dibh.pending = :pending')
            ->setParameter('pending', false);

        return $qb->getQuery()->getResult();
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
}