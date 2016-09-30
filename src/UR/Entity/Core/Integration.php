<?php

namespace UR\Entity\Core;

use UR\Model\Core\Integration as IntegrationModel;
use UR\Model\Core\IntegrationGroupInterface;

class Integration extends IntegrationModel
{
    protected $id;
    protected $name;
    protected $type;
    protected $url;

    /**
     * @var IntegrationGroupInterface
     */
    protected $integrationGroup;

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