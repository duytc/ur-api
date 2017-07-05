<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{
    /** @var AlertManagerInterface */
    protected $alertManager;
    /** @var PublisherManagerInterface */
    protected $publisherManager;
    /** @var DataSourceManagerInterface */
    protected $dataSourceManager;

    public function __construct(AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager, DataSourceManagerInterface $dataSourceManager)
    {
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
        $this->dataSourceManager = $dataSourceManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, $details, $dataSourceId = null)
    {
        $publisher = $this->publisherManager->findPublisher($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            throw new \Exception(sprintf('Not found that publisher %s', $publisherId));
        }

        if (null !== $dataSourceId) {
            /** @var null|DataSourceInterface $dataSource */
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if ($dataSource instanceof DataSourceInterface) {
                if ($dataSource->getPublisherId() !== $publisherId) {
                    $dataSource = null; // make sure correct permission on data source for publisher
                }
            }
        } else {
            $dataSource = null;
        }

        /* add type alert */
        $type = array_key_exists($alertCode, Alert::$ALERT_CODE_TO_TYPE_MAP) ? Alert::$ALERT_CODE_TO_TYPE_MAP[$alertCode] : Alert::ALERT_TYPE_INFO;

        $alert = new Alert();
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setDetail($details);
        $alert->setDataSource($dataSource);
        $alert->setType($type);

        $this->alertManager->save($alert);
    }
}