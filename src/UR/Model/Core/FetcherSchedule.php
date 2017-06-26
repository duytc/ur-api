<?php

namespace UR\Model\Core;


class FetcherSchedule implements FetcherScheduleInterface
{
    protected $id;

    /** @var  DataSourceIntegrationBackfillHistoryInterface */
    protected $backFillHistory;

    /** @var  DataSourceIntegrationScheduleInterface */
    protected $dataSourceIntegrationSchedule;

    /**
     * FetcherSchedule constructor.
     */
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getBackFillHistory()
    {
        return $this->backFillHistory;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillHistory($backFillHistory)
    {
        $this->backFillHistory = $backFillHistory;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceIntegrationSchedule()
    {
        return $this->dataSourceIntegrationSchedule;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceIntegrationSchedule($dataSourceIntegrationSchedule)
    {
        $this->dataSourceIntegrationSchedule = $dataSourceIntegrationSchedule;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}