<?php

namespace UR\Entity\Core;

use UR\Model\Core\Integration as IntegrationModel;
use UR\Model\Core\IntegrationTagInterface;

class Integration extends IntegrationModel
{
    protected $id;
    protected $name;
    protected $canonicalName;
    protected $params;
    protected $enableForAllUsers;
    protected $integrationPublishers;

    /** @var  IntegrationTagInterface[] */
    protected $integrationTags;
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