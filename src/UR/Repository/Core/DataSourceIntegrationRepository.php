<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;

class DataSourceIntegrationRepository extends EntityRepository implements DataSourceIntegrationRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findByIntegrationCanonicalName($canonicalName)
    {
        $qb = $this->createQueryBuilder('di')
            ->join('di.integration', 'it')
            ->where('it.canonicalName = :canonicalName')
            ->setParameter('canonicalName', $canonicalName);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getIntegrationBySchedule()
    {
        $currentDate = new \DateTime();
        $qb = $this->createQueryBuilder('di')
            ->where('dateadd(di.lastExecutedAt, INTERVAL di.schedule HOUR) <= :currentDate')
            ->setParameter('currentDate', $currentDate);
        return $qb->getQuery()->getResult();
    }
}