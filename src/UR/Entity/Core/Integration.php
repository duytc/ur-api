<?php

namespace UR\Entity\Core;

use UR\Model\Core\Integration as IntegrationModel;

class Integration extends IntegrationModel
{
    protected $id;
    protected $name;
    protected $canonicalName;
    protected $type;
    protected $method;
    protected $url;

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