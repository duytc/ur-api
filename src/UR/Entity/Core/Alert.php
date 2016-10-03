<?php

namespace UR\Entity\Core;

use UR\Model\Core\Alert as AlertModel;
use UR\Model\Core\DataSourceEntryInterface;

class Alert extends AlertModel
{
    protected $id;
    protected $type;
    protected $isRead;
    protected $title;
    protected $message;
    protected $createdDate;

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