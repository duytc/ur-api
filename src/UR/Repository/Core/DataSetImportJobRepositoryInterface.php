<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;

interface DataSetImportJobRepositoryInterface extends ObjectRepository
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
     * @param int $dataSetId
     * @return mixed
     */
    public function getAllExecuteImportJobsByDataSetId($dataSetId);
}