<?php

namespace UR\Entity\Core;

use UR\Model\Core\IntegrationGroup as IntegrationGroupModel;

class IntegrationGroup extends IntegrationGroupModel
{
    protected $id;
    protected $name;
    protected $integrations;

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