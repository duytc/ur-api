<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Entity\Core\Alert;

class ProcessAlert implements ProcessAlertInterface
{
    protected $alertManager;
    protected $dataSourceEntryManager;
    protected $connectedDataSource;
    protected $publisherManager;
    protected  $alertCodes;

    public function __construct($alertCodes, AlertManagerInterface $alertManager, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSource, PublisherManagerInterface $publisherManager)
    {
        $this->alertCodes =  $alertCodes;
        $this->alertManager = $alertManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSource = $connectedDataSource;
        $this->publisherManager = $publisherManager;
    }

    public function createAlert($alertCode, $publisherId, $messageDetail)
    {

    }
}