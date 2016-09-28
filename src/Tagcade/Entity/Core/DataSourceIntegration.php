<?php

namespace Tagcade\Entity\Core;

use Tagcade\Model\Core\DataSourceIntegration as DataSourceIntegrationModel;
use Tagcade\Model\Core\DataSourceInterface;
use Tagcade\Model\Core\IntegrationInterface;

class DataSourceIntegration extends DataSourceIntegrationModel
{
    protected $id;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var IntegrationInterface
     */
    protected $integration;

    protected $username;
    protected $password;
    protected $schedule;

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