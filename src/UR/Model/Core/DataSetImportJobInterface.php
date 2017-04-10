<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSetImportJobInterface extends ModelInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet(DataSetInterface $dataSet);

    /**
     * @return string
     */
    public function getJobId();

    /**
     * @param string $jobId
     * @return self
     */
    public function setJobId(string $jobId);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     * @return self
     */
    public function setCreatedDate($createdDate);
}
