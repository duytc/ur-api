<?php

namespace UR\Service\Alert;


use Doctrine\Common\Collections\Collection;
use Exception;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{
    /** @var AlertManagerInterface */
    protected $alertManager;
    /** @var PublisherManagerInterface */
    protected $publisherManager;
    /** @var DataSourceManagerInterface */
    protected $dataSourceManager;

    /** @var  OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    public function __construct(AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager, DataSourceManagerInterface $dataSourceManager, OptimizationIntegrationManagerInterface $optimizationRuleManager)
    {
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
        $this->dataSourceManager = $dataSourceManager;
        $this->optimizationIntegrationManager = $optimizationRuleManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, $details, $dataSourceId = null, $optimizationIntegrationId = null)
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

        if (null !== $optimizationIntegrationId) {
            /** @var null|OptimizationIntegrationInterface $optimizationIntegration */
            $optimizationIntegration = $this->optimizationIntegrationManager->find($optimizationIntegrationId);
            if ($optimizationIntegration instanceof OptimizationRuleInterface) {
                if ($optimizationIntegration->getOptimizationRule()->getPublisher()->getId() !== $publisherId) {
                    $optimizationIntegration = null; // make sure correct permission on optimization for publisher
                }
            }
        } else {
            $optimizationIntegration = null;
        }

        /* add type alert */
        $type = array_key_exists($alertCode, Alert::$ALERT_CODE_TO_TYPE_MAP) ? Alert::$ALERT_CODE_TO_TYPE_MAP[$alertCode] : Alert::ALERT_TYPE_INFO;

        $alert = new Alert();
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setDetail($details);
        $alert->setDataSource($dataSource);
        $alert->setOptimizationIntegration($optimizationIntegration);
        $alert->setType($type);
        $alert->setIsSent(false);

        $this->alertManager->save($alert);
    }

    /**
     * @param AlertParams $alertParam
     * @return mixed
     * @throws Exception
     */
    public function updateStatusOrDeleteAlertsByParams(AlertParams $alertParam)
    {
        $action =  $alertParam->getAction();

        switch ($action)
        {
            case ProcessAlertInterface::DELETE_ACTION_KEY:
                return $this->deleteAlertsByParams($alertParam);
            case  ProcessAlertInterface::MARK_AS_UNREAD_ACTION_KEY:
                return $this->markAlertsAsUnReadByParams($alertParam);
            case ProcessAlertInterface::MARK_AS_READ_ACTION_KEY:
                return $this->markAlertsAsReadByParams($alertParam);
            default:
                throw new \Exception(sprintf('System does not support action: %s', $action));
        }
    }

    protected function deleteAlertsByParams(AlertParams $alertParams)
    {
        $alertIds = $this->getAlertIds($alertParams);

        return $this->alertManager->deleteAlertsByIds($alertIds);

    }

    protected function markAlertsAsReadByParams(AlertParams $alertParams)
    {
        $alertIds = $this->getAlertIds($alertParams);

        return $this->alertManager->updateMarkAsReadByIds($alertIds);

    }

    protected function markAlertsAsUnReadByParams(AlertParams $alertParams)
    {
        $alertIds = $this->getAlertIds($alertParams);

        return $this->alertManager->updateMarkAsUnreadByIds($alertIds);

    }

    /**
     * @param AlertParams $alertParams
     * @return array|mixed
     */
    protected function getAlertIds(AlertParams $alertParams)
    {
        $alertIds = $alertParams->getAlertIds();
        if (empty($alertIds)) {
            $alerts = $this->alertManager->getAlertsByParams($alertParams);
            if ($alerts instanceof Collection) {
                $alerts = $alerts->toArray();
            }

            $alertIds = array_map(function ($alert) {
                /**@var AlertInterface $alert * */
                return $alert->getId();
            }, $alerts);
        }

        return $alertIds;
    }
}