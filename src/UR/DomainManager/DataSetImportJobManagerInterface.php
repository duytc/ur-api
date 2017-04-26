<?php

namespace UR\DomainManager;


use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;

interface DataSetImportJobManagerInterface extends ManagerInterface
{
    /**
     * @param int $dataSetId
     * @return mixed
     */
    public function getExecuteImportJobByDataSetId($dataSetId);

    /**
     * @param string $jobId
     * @return mixed
     */
    public function getExecuteImportJobByJobId($jobId);

    /**
     * @param DataSetInterface $dataSet
     * @param string $jobName
     * @param array $jobData
     * @return DataSetImportJobInterface
     */
    public function createNewDataSetImportJob(DataSetInterface $dataSet, $jobName, array $jobData);
}