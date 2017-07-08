<?php

namespace UR\Entity\Core;


use UR\Model\Core\Alert as AlertModel;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\UserEntityInterface;

class Alert extends AlertModel
{
    protected $id;
    protected $code;
    protected $isRead;
    protected $detail;
    protected $createdDate;
    protected $type;
    protected $isSent;

    /**
     * @var UserEntityInterface
     */
    protected $publisher;

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