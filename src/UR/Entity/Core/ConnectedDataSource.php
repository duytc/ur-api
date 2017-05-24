<?php

namespace UR\Entity\Core;

use UR\Model\Core\ConnectedDataSource as ConnectedDataSourceModel;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\LinkedMapDataSetInterface;

class ConnectedDataSource extends ConnectedDataSourceModel
{
    protected $id;
    protected $name;
    protected $mapFields;
    protected $filters;
    protected $transforms;
    protected $requires;
    protected $alertSetting;
    protected $temporaryFields;
    protected $lastActivity;
    protected $totalRow;
    protected $jobExpirationDate;

    /** @var  bool $replayData */
    protected $replayData;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var LinkedMapDataSetInterface[]
     */
    protected $linkedMapDataSets;

    /**
     * @var DataSetImportJobInterface[]
     */
    protected $dataSetImportJobs;

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