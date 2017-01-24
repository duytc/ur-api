<?php

namespace UR\Model\Core;

class DataSourceIntegration implements DataSourceIntegrationInterface
{
    protected $id;

    /** @var array */
    protected $params;
    protected $schedule;
    protected $active;
    protected $lastExecutedAt;

    /** @var DataSourceInterface */
    protected $dataSource;

    /** @var IntegrationInterface */
    protected $integration;

    public function __construct()
    {
        $this->active = true;
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
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @inheritdoc
     */
    public function setDataSource(DataSourceInterface $dataSource)
    {
        $this->dataSource = $dataSource;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @inheritdoc
     */
    public function setIntegration(IntegrationInterface $integration)
    {
        $this->integration = $integration;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @inheritdoc
     */
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @inheritdoc
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLastExecutedAt()
    {
        return $this->lastExecutedAt;
    }

    /**
     * @inheritdoc
     */
    public function setLastExecutedAt($lastExecutedAt)
    {
        $this->lastExecutedAt = $lastExecutedAt;
    }
}