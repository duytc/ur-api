<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceIntegrationSchedule as DataSourceIntegrationScheduleModel;
use UR\Model\Core\DataSourceIntegrationInterface;

class DataSourceIntegrationSchedule extends DataSourceIntegrationScheduleModel
{
    protected $id;

    protected $uuid;

    /** @var  \DateTime */
    protected $nextExecutedAt;

    /** @var  \DateTime */
    protected $finishedAt;

    /** @var  \DateTime */
    protected $queuedAt;

    protected $scheduleType;

    /** @var DataSourceIntegrationInterface */
    protected $dataSourceIntegration;

    /** @var  integer */
    protected $status;

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