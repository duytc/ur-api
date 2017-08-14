<?php

namespace UR\Model\Core;

class DataSourceIntegrationSchedule implements DataSourceIntegrationScheduleInterface
{
    protected $id;

    protected $uuid;
    /** @var \DateTime */
    protected $nextExecutedAt;

    /** @var  \DateTime */
    protected $queuedAt;

    /** @var  \DateTime */
    protected $finishedAt;

    protected $scheduleType;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var  integer */
    protected $status;

    public function __construct()
    {
    }

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
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @inheritdoc
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNextExecutedAt()
    {
        return $this->nextExecutedAt;
    }

    /**
     * @inheritdoc
     */
    public function setNextExecutedAt(\DateTime $nextNextExecutedAt)
    {
        $this->nextExecutedAt = $nextNextExecutedAt;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getScheduleType()
    {
        return $this->scheduleType;
    }

    /**
     * @inheritdoc
     */
    public function setScheduleType($scheduleType)
    {
        $this->scheduleType = $scheduleType;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceIntegration()
    {
        return $this->dataSourceIntegration;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        $this->dataSourceIntegration = $dataSourceIntegration;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getQueuedAt()
    {
        return $this->queuedAt;
    }

    /**
     * @inheritdoc
     */
    public function setQueuedAt($queuedAt)
    {
        $this->queuedAt = $queuedAt;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFinishedAt()
    {
        return $this->finishedAt;
    }

    /**
     * @inheritdoc
     */
    public function setFinishedAt($finishedAt)
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }
}
