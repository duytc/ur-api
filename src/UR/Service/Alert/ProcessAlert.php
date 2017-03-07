<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{

    const FILE_NAME = 'fileName';
    const DATA_SOURCE_NAME = 'dataSourceName';
    const DATA_SOURCE_ID = 'dataSourceId';
    const FORMAT_FILE = 'formatFile';
    const DATA_SET_NAME = 'dataSetName';
    const DATA_SET_ID = 'dataSetId';
    const IMPORT_ID = 'importId';
    const ENTRY_ID = 'entryId';

    const MESSAGE = 'message';
    const DETAILS = 'detail';
    const ERROR = 'error';

    protected $alertManager;
    protected $publisherManager;

    public function __construct(AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager)
    {
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, $message, $details)
    {
        $publisher = $this->publisherManager->findPublisher($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            throw new \Exception(sprintf('Not found that publisher %s', $publisherId));
        }

        $alert = new Alert();
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setMessage([self::MESSAGE => $message]);
        $alert->setDetail([self::DETAILS => $message]);

        $this->alertManager->save($alert);
    }
}