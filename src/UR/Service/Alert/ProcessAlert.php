<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{
    protected $alertManager;
    protected $publisherManager;
    protected $alertCodes;

    public function __construct(array $alertCodes, AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager)
    {
        $this->alertCodes = [AlertParams::UPLOAD_DATA_SUCCESS,
            AlertParams::UPLOAD_DATA_FAILURE,
            AlertParams::UPLOAD_DATA_WARNING,
            AlertParams::IMPORT_DATA_SUCCESS,
            AlertParams::IMPORT_DATA_FAILURE
        ];
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, $messageDetail)
    {
        if (!in_array($alertCode, $this->alertCodes)) {
            throw new \Exception('Alert code is not valid');
        }

        $publisher = $this->publisherManager->findPublisher($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            throw new \Exception(sprintf('Not found that publisher %s', $publisherId));
        }

        $alert = new Alert();
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setMessage($messageDetail);
        $this->alertManager->save($alert);
    }
}