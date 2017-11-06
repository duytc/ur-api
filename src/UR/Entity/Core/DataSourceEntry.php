<?php

namespace UR\Entity\Core;

use UR\Model\Core\DataSourceEntry as DataSourceEntryModel;

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
    protected $dataSource;
    protected $importHistories;

    protected $missingDate;
    protected $dateRangeBroken;
    protected $startDate;
    protected $endDate;
    protected $dates;
    protected $removeHistory;
    protected $separable;
    protected $chunks;

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