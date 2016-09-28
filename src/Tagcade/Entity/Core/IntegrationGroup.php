<?php

namespace Tagcade\Entity\Core;

use Tagcade\Model\Core\IntegrationGroup as IntegrationGroupModel;
use Tagcade\Model\Core\DataSourceInterface;

class IntegrationGroup extends IntegrationGroupModel
{
    protected $id;
    protected $name;

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