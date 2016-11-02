<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceEntryImportHistory as DataSourceEntryImportHistoryModel;
use UR\Model\Core\ImportHistoryInterface;

class DataSourceEntryImportHistory extends DataSourceEntryImportHistoryModel
{
    protected $id;
    protected $status;
    protected $importedDate;
    protected $description;

    /**
     * @var ImportHistoryInterface
     */
    protected $importHistory;

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