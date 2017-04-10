<?php

namespace UR\Model\Core;

class DataSetImportJob implements DataSetImportJobInterface
{
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
}