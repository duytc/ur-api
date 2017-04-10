<?php

namespace UR\Service\Import;


use UR\Entity\Core\DataSetImportJob;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;

class CreateDataSetImportJobEntity
{

    /**
     * @param DataSetInterface $dataSet
     * @return DataSetImportJobInterface
     */
    public function createDataSetImportJobEntity(DataSetInterface $dataSet)
    {
        $jobId = bin2hex(random_bytes(20));
        $dataSetImportJobEntity = (new DataSetImportJob())
            ->setDataSet($dataSet)
            ->setJobId($jobId);

        return $dataSetImportJobEntity;
    }
}