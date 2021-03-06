<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceIntegration as DataSourceIntegrationModel;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\IntegrationInterface;

class DataSourceIntegration extends DataSourceIntegrationModel
{
    protected $id;

    protected $params;
    protected $schedule;
    protected $active;

    /** @var DataSourceInterface */
    protected $dataSource;

    /** @var IntegrationInterface */
    protected $integration;

    /** @var DataSourceIntegrationScheduleInterface[] */
    protected $dataSourceIntegrationSchedules;

    /** @var  DataSourceIntegrationBackfillHistoryInterface[] */
    protected $dataSourceIntegrationBackFillHistories;

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