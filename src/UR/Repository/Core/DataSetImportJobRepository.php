<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;

class DataSetImportJobRepository extends EntityRepository implements DataSetImportJobRepositoryInterface
{

    /**
     * @inheritdoc
     */
    public function getExecuteImportJobByDataSetId($dataSetId)
    {
        $qb = $this->createQueryBuilder('dij')
            ->where('dij.dataSet=:dataSet')
            ->setParameter('dataSet', $dataSetId)
            ->orderBy('dij.createdDate, dij.id', 'ASC')
            ->setMaxResults(1);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @inheritdoc
     */
    public function getExecuteImportJobByJobId($jobId)
    {
        $qb = $this->createQueryBuilder('dij')
            ->where('dij.job=:job')
            ->setParameter('job', $jobId);

        return $qb->getQuery()->getSingleResult();
    }
}