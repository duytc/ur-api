<?php

namespace UR\Model\Core;

class DataSetImportJob implements DataSetImportJobInterface
{
    const JOB_TYPE_IMPORT = 'import';
    const JOB_TYPE_ALTER = 'alter';
    /**
     * @var int
     */
    protected $id;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var string
     */
    protected $jobId;

    protected $jobName;

    protected $jobData;

    protected $jobType;

    protected $createdDate;

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @inheritdoc
     */
    public function setDataSet(DataSetInterface $dataSet)
    {
        $this->dataSet = $dataSet;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJobId()
    {
        return $this->jobId;
    }

    /**
     * @inheritdoc
     */
    public function setJobId(string $jobId)
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getJobName()
    {
        return $this->jobName;
    }

    /**
     * @param mixed $jobName
     * @return $this
     */
    public function setJobName($jobName)
    {
        $this->jobName = $jobName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getJobData()
    {
        return $this->jobData;
    }

    /**
     * @param mixed $jobData
     * @return $this
     */
    public function setJobData($jobData)
    {
        $this->jobData = $jobData;
        return $this;
    }

    /**
     * @param DataSetInterface $dataSet
     * @param $jobName
     * @param $jobType
     * @param array $jobData
     * @return DataSetImportJobInterface
     */
    public static function createEmptyDataSetImportJob(DataSetInterface $dataSet, $jobName, $jobType, array $jobData)
    {
        $jobId = bin2hex(random_bytes(20));
        $dataSetImportJob = (new \UR\Entity\Core\DataSetImportJob())
            ->setJobName($jobName)
            ->setDataSet($dataSet)
            ->setJobType($jobType)
            ->setJobData($jobData)
            ->setJobId($jobId);

        return $dataSetImportJob;
    }

    /**
     * @return mixed
     */
    public function getJobType()
    {
        return $this->jobType;
    }

    /**
     * @inheritdoc
     */
    public function setJobType($jobType)
    {
        $this->jobType = $jobType;
        return $this;
    }
}