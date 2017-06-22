<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceIntegrationBackfillHistory as DataSourceIntegrationBackfillHistoryModel;
use UR\Model\Core\DataSourceIntegrationInterface;

class DataSourceIntegrationBackfillHistory extends DataSourceIntegrationBackfillHistoryModel
{
    protected $id;

    protected $lastExecutedAt;

    // back fill feature
    protected $backFillStartDate;
    protected $backFillEndDate;

    /** @var DataSourceIntegrationInterface[] */
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