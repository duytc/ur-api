<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSource as DataSourceModel;

class DataSource extends DataSourceModel
{
    protected $id;
    protected $publisher;
    protected $name;
    protected $format;

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