<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;

class DataSourceIntegrationScheduleRepository extends EntityRepository implements DataSourceIntegrationScheduleRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findToBeExecuted()
    {
        $currentDate = new \DateTime();

        $qb = $this->createQueryBuilder('dis')
            ->join('dis.dataSourceIntegration', 'dsi')
            ->where('dis.executedAt <= :currentDate')
            ->andWhere('dsi.active = :dataSourceActive')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('dataSourceActive', true);

        return $qb->getQuery()->getResult();
    }
}