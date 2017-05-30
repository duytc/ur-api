<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
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
            ->setParameter('dataSource', $dataSource);

        return $qb->getQuery()->getResult();
    }
}