<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSetImportJobInterface extends ModelInterface
{
    const CONNECTED_DATA_SOURCE_ID = 'connectedDataSourceId';
    const DATA_SOURCE_ENTRY_ID = 'dataSourceEntryIds';

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


    /**
     * @return mixed
     */
    public function getJobName();

    /**
     * @param mixed $jobName
     * @return self
     */
    public function setJobName($jobName);

    /**
     * @return mixed
     */
    public function getJobData();

    /**
     * @param mixed $jobData
     * @return self
     */
    public function setJobData($jobData);


    /**
     * @return mixed
     */
    public function getJobType();

    /**
     * @param mixed $jobType
     * @return self
     */
    public function setJobType($jobType);

    /**
     * @return mixed
     */
    public function getWaitFor();

    /**
     * @param mixed $waitFor
     * @return self
     */
    public function setWaitFor($waitFor);

    /**
     * @return ConnectedDataSourceInterface
     */
    public function getConnectedDataSource();

    /**
     * @param mixed $connectedDataSource
     * @return self
     */
    public function setConnectedDataSource($connectedDataSource);
}
