<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSource as DataSourceModel;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\User\UserEntityInterface;

class DataSource extends DataSourceModel
{
    protected $id;
    protected $name;
    protected $format;
    protected $alertSetting;
    protected $apiKey;
    protected $urEmail;
    protected $enable;

    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var DataSourceIntegrationInterface[]
     */
    protected $dataSourceIntegrations;

    /**
     * @var DataSourceEntryInterface[]
     */
    protected $dataSourceEntries;

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