<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;

class ProcessAlert implements ProcessAlertInterface
{
    protected $alertManager;
    protected $publisherManager;
    protected $alertCodes;

    public function __construct($alertCodes, AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager)
    {
        $this->alertCodes = $alertCodes;
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, $messageDetail)
    {

    }
}