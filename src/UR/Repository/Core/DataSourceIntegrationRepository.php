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
}