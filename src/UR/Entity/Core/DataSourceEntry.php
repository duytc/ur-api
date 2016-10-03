<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceEntry as DataSourceEntryModel;
use UR\Model\Core\DataSourceInterface;

class DataSourceEntry extends DataSourceEntryModel
{
    protected $id;
    protected $receivedDate;
    protected $valid;
    protected $path;
    protected $metaData;
    protected $format;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

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