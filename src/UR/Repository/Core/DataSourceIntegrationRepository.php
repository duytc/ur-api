<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceIntegrationInterface;

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
     * @param integer $dataSourceId
     * @return array|DataSourceIntegrationInterface[]
     */
    public function findByDataSource($dataSourceId)
    {
        $qb = $this->createQueryBuilder('di')
            ->where('di.dataSource = :dataSourceId')
            ->setParameter('dataSourceId', $dataSourceId)
            ->distinct();

        return $qb->getQuery()->getResult();
    }
}