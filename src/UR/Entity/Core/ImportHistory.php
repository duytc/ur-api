<?php

namespace UR\Entity\Core;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\ImportHistory as ImportHistoryModel;

class ImportHistory extends ImportHistoryModel
{
    protected $id;
    protected $createdDate;
    protected $description;

    /**
     * @var ConnectedDataSourceInterface
     */
    protected $connectedDataSource;

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