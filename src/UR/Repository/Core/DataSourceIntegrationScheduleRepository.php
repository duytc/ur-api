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
            ->where('dis.executedAt <= :currentDate')
            ->setParameter('currentDate', $currentDate);
        return $qb->getQuery()->getResult();
    }
}