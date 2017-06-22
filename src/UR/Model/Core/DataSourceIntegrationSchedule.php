<?php

namespace UR\Model\Core;

class DataSourceIntegrationSchedule implements DataSourceIntegrationScheduleInterface
{
    protected $id;

    protected $uuid;
    /** @var \DateTime */
    protected $executedAt;
    protected $scheduleType;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var  bool */
    protected $isRunning;

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
    public function getExecutedAt()
    {
        return $this->executedAt;
    }

    /**
     * @inheritdoc
     */
    public function setExecutedAt(\DateTime $executedAt)
    {
        $this->executedAt = $executedAt;
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
    public function getIsRunning()
    {
        return $this->isRunning;
    }

    /**
     * @inheritdoc
     */
    public function setIsRunning($isRunning)
    {
        $this->isRunning = $isRunning;

        return $this;
    }
}
