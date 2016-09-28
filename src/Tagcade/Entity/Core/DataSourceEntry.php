<?php

namespace Tagcade\Entity\Core;

use Tagcade\Model\Core\DataSourceEntry as DataSourceEntryModel;
use Tagcade\Model\Core\DataSourceInterface;

class DataSourceEntry extends DataSourceEntryModel
{
    protected $id;
    protected $receivedDate;
    protected $valid;
    protected $path;
    protected $metaData;

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