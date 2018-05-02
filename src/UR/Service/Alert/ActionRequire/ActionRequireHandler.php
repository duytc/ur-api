<?php

namespace UR\Service\Alert\ActionRequire;

use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Service\PublicSimpleException;
use UR\Worker\Manager;

class ActionRequireHandler implements ActionRequireHandlerInterface
{
    /** @var OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    /** @var Manager */
    private $manager;

    /**
     * ActionRequireHandler constructor.
     * @param OptimizationIntegrationManagerInterface $optimizationIntegrationManager
     * @param Manager $manager
     */
    public function __construct(OptimizationIntegrationManagerInterface $optimizationIntegrationManager, Manager $manager)
    {
        $this->optimizationIntegrationManager = $optimizationIntegrationManager;
        $this->manager = $manager;
    }

    /**
     * @inheritdoc
     */
    public function handleActionRequired($actionName, $actionData)
    {
        try {
            switch ($actionName) {
                case ActionRequireHandlerInterface::ACTIVE_OPTIMIZATION_INTEGRATION:
                    $this->activeOptimizationIntegration($actionData);
                    break;
                case ActionRequireHandlerInterface::REJECT_OPTIMIZATION_INTEGRATION:
                    $this->rejectOptimizationIntegration($actionData);
                    break;
                default:
                    throw new PublicSimpleException(sprintf('Do not support action type %s', $actionName));
            }
        } catch (\Exception $e)  {
            throw $e;
        }

        return sprintf("Successfully executing action %s", $actionName);
    }

    /**
     * @param $actionData
     * @throws PublicSimpleException
     */
    private function activeOptimizationIntegration($actionData)
    {
        $optimizationIntegrationId = array_key_exists(ActionRequireHandlerInterface::PARAM_OPTIMIZATION_INTEGRATION_ID, $actionData) ? $actionData[ActionRequireHandlerInterface::PARAM_OPTIMIZATION_INTEGRATION_ID] : "";
        $optimizationIntegration = $this->optimizationIntegrationManager->find($optimizationIntegrationId);

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            throw new PublicSimpleException(sprintf('No permission on %s', $optimizationIntegrationId));
        }

        if (!$optimizationIntegration->isUserConfirm()) {
            $optimizationIntegration->setActive(OptimizationIntegrationInterface::ACTIVE_APPLY);
            //Call Activate3rdPartnerScoringServiceIntegrationListener
            $this->optimizationIntegrationManager->save($optimizationIntegration);
        } else {
            $this->manager->activateThe3PartnerScoringServiceIntegration($optimizationIntegration->getOptimizationRule()->getId(), $optimizationIntegration->getId());
        }
    }

    /**
     * @param $actionData
     * @throws PublicSimpleException
     */
    private function rejectOptimizationIntegration($actionData)
    {
        $optimizationIntegrationId = array_key_exists(ActionRequireHandlerInterface::PARAM_OPTIMIZATION_INTEGRATION_ID, $actionData) ? $actionData[ActionRequireHandlerInterface::PARAM_OPTIMIZATION_INTEGRATION_ID] : "";
        $optimizationIntegration = $this->optimizationIntegrationManager->find($optimizationIntegrationId);

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            throw new PublicSimpleException(sprintf('No permission on %s', $optimizationIntegrationId));
        }
        if ($optimizationIntegration->isUserConfirm()) {
            $optimizationIntegration->setActive(OptimizationIntegrationInterface::ACTIVE_REJECT);
            $this->optimizationIntegrationManager->save($optimizationIntegration);
        }
    }
}