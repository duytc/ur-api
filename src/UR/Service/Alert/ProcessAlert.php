<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;

class ProcessAlert implements ProcessAlertInterface
{

    const NEW_DATA_IS_RECEIVED_FROM_UPLOAD = 100;
    const NEW_DATA_IS_RECEIVED_FROM_EMAIL = 101;
    const NEW_DATA_IS_RECEIVED_FROM_API = 102;
    const NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT = 103;
    const NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT = 104;
    const NEW_DATA_IS_AD_TO_CONNECTED_DATA_SOURCE = 200;
    const DATA_IMPORT_FAILS = 201;

    protected $alertManager;
    protected $publisherManager;

    /**
     * Status codes translation table.
     *
     * @var array
     */
    public static $alertCodes = array(
        100 => 'New data is received from Upload',      // error codes for dataSource
        101 => 'New data is received from Email',
        102 => 'New data is received from API',
        103 => 'New data is received from Email in wrong format',
        104 => 'New data is received API in wrong format',
        200 => 'New data is add to the connected data source',              // error codes for dataSet
        201 => 'Data import fails',
    );

    public function __construct(AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager)
    {
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