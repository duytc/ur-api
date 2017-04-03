<?php

namespace UR\Entity\Core;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSet as DataSetModel;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Model\User\UserEntityInterface;

class DataSet extends DataSetModel
{
    protected $id;
    protected $name;
    protected $dimensions;
    protected $metrics;
    protected $createdDate;
    protected $allowOverwriteExistingData;

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