<?php

namespace UR\Model\Core;

class DataSourceIntegrationBackfillHistory implements DataSourceIntegrationBackfillHistoryInterface
{
    protected $id;

    /** @var array */
    protected $executedAt;

    // back fill feature
    /** @var \DateTime|null */
    protected $backFillStartDate;
    /** @var \DateTime|null */
    protected $backFillEndDate;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var */
    protected $pending;

    public function __construct()
    {
        // back fill feature
        $this->backFillStartDate = null;
        $this->backFillEndDate = null;
        $this->executedAt = null;
        $this->pending = false;
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
    public function getExecutedAt()
    {
        return $this->executedAt;
    }

    /**
     * @inheritdoc
     */
    public function setExecutedAt($executedAt)
    {
        $this->executedAt = $executedAt;
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
    public function getPending()
    {
        return $this->pending;
    }

    /**
     * @inheritdoc
     */
    public function setPending($pending)
    {
        $this->pending = $pending;

        return $this;
    }
}