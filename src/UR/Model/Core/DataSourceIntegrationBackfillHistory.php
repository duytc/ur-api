<?php

namespace UR\Model\Core;

class DataSourceIntegrationBackfillHistory implements DataSourceIntegrationBackfillHistoryInterface
{
    protected $id;

    /** @var array */
    protected $lastExecutedAt;

    // back fill feature
    /** @var \DateTime|null */
    protected $backFillStartDate;
    /** @var \DateTime|null */
    protected $backFillEndDate;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var */
    protected $isRunning;

    public function __construct()
    {
        // back fill feature
        $this->backFillStartDate = null;
        $this->backFillEndDate = null;
        $this->lastExecutedAt = null;
        $this->isRunning = false;
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
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBackFillStartDate()
    {
        return $this->backFillStartDate;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillStartDate($backFillStartDate)
    {
        $this->backFillStartDate = $backFillStartDate;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBackFillEndDate()
    {
        return $this->backFillEndDate;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillEndDate($backFillEndDate)
    {
        $this->backFillEndDate = $backFillEndDate;

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