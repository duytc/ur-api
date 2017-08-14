<?php

namespace UR\Entity\Core;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSet as DataSetModel;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Model\User\UserEntityInterface;

class DataSet extends DataSetModel
{
    protected $id;
    protected $name;
    protected $dimensions;
    protected $metrics;
    protected $createdDate;
    protected $totalRow;
    protected $allowOverwriteExistingData;
    protected $lastActivity;
    protected $importHistories;
    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var ConnectedDataSourceInterface[]
     */
    protected $connectedDataSources;

    /**
     * @var LinkedMapDataSetInterface[]
     */
    protected $linkedMapDataSets;

    protected $numConnectedDataSourceChanges;
    protected $numChanges;
    protected $mapBuilderEnabled;

    /**
     * @var MapBuilderConfigInterface[]
     */
    protected $mapBuilderConfigs;
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