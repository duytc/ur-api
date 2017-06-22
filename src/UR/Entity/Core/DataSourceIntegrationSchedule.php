<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceIntegrationSchedule as DataSourceIntegrationScheduleModel;
use UR\Model\Core\DataSourceIntegrationInterface;

class DataSourceIntegrationSchedule extends DataSourceIntegrationScheduleModel
{
    protected $id;

    protected $uuid;
    protected $executedAt;
    protected $scheduleType;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    protected $isRunning;

    /**
     * @inheritdoc
     *
     * inherit constructor for inheriting all default initialized value
     */
    public function __construct()
    {
        parent::__construct();
    }
}