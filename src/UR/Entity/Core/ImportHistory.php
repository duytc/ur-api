<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistory as ImportHistoryModel;

class ImportHistory extends ImportHistoryModel
{
    protected $id;
    protected $createdDate;
    protected $description;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var DataSourceEntryInterface
     */
    protected $dataSourceEntry;

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