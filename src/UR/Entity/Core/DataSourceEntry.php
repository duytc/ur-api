<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceEntry as DataSourceEntryModel;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;

class DataSourceEntry extends DataSourceEntryModel
{
    protected $id;
    protected $receivedDate;
    protected $isValid;
    protected $isActive;
    protected $path;
    protected $fileName;
    protected $metaData;
    protected $receivedVia;
    protected $hashFile;
    protected $totalRow;
    protected $fileExtension;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var ImportHistoryInterface[]
     */
    protected $importHistories;

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