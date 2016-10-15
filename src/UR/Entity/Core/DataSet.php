<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSet as DataSetModel;
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\UserEntityInterface;

class DataSet extends DataSetModel
{
    protected $id;
    protected $name;
    protected $dimensions;
    protected $metrics;
    protected $createdDate;

    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var IntegrationInterface[]
     */
    protected $dataSourceIntegrations;

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