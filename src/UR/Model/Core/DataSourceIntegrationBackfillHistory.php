<?php

namespace UR\Model\Core;

class DataSourceIntegrationBackfillHistory implements DataSourceIntegrationBackfillHistoryInterface
{
    public static $SUPPORTED_STATUS = [
        self::FETCHER_STATUS_NOT_RUN,
        self::FETCHER_STATUS_PENDING,
        self::FETCHER_STATUS_FINISHED,
        self::FETCHER_STATUS_FAILED
    ];

    protected $id;

    /** @var \DateTime */
    protected $queuedAt;

    /** @var  \DateTime */
    protected $finishedAt;

    // back fill feature
    /** @var \DateTime|null */
    protected $backFillStartDate;
    /** @var \DateTime|null */
    protected $backFillEndDate;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var integer*/
    protected $status;

    /** @var  boolean */
    protected $autoCreate;

    public function __construct()
    {
        // back fill feature
        $this->backFillStartDate = null;
        $this->backFillEndDate = null;
        $this->queuedAt = null;
        $this->status = DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN;
        $this->autoCreate = false;
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

    /**
     * @inheritdoc
     */
    public function getAutoCreate()
    {
        return $this->autoCreate;
    }

    /**
     * @inheritdoc
     */
    public function setAutoCreate($autoCreate)
    {
        $this->autoCreate = $autoCreate;

        return $this;
    }
}