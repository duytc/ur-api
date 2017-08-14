<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceIntegrationBackfillHistory as DataSourceIntegrationBackfillHistoryModel;
use UR\Model\Core\DataSourceIntegrationInterface;

class DataSourceIntegrationBackfillHistory extends DataSourceIntegrationBackfillHistoryModel
{
    protected $id;

    /** @var  \DateTime */
    protected $queuedAt;

    /** @var  \DateTime */
    protected $finishedAt;

    // back fill feature
    protected $backFillStartDate;
    protected $backFillEndDate;

    /** @var DataSourceIntegrationInterface[] */
    protected $dataSourceIntegration;

    /** @var  integer */
    protected $status;

    /** var boolean */
    protected $autoCreate;

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