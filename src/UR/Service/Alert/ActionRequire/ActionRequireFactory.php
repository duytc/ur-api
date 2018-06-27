<?php

namespace UR\Service\Alert\ActionRequire;

use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\OptimizationRule\OptimizationRuleScoreServiceInterface;
use UR\Service\StringUtilTrait;
use UR\Worker\Manager;

class ActionRequireFactory implements ActionRequireFactoryInterface
{
    use StringUtilTrait;

    /** @var Manager */
    private $manager;

    /** @var AlertManagerInterface */
    private $alertManager;

    /** @var OptimizationRuleScoreServiceInterface */
    private $optimizationRuleScoreService;

    /**
     * ActionRequireFactory constructor.
     * @param Manager $manager
     * @param AlertManagerInterface $alertManager
     * @param OptimizationRuleScoreServiceInterface $optimizationRuleScoreService
     */
    public function __construct(Manager $manager, AlertManagerInterface $alertManager, OptimizationRuleScoreServiceInterface $optimizationRuleScoreService)
    {
        $this->manager = $manager;
        $this->alertManager = $alertManager;
        $this->optimizationRuleScoreService = $optimizationRuleScoreService;
    }

    /**
     * @inheritdoc
     */
    public function createActionRequireAlert($optimizationIntegration, $extraData = [])
    {
        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return false;
        }

        return $this->createAlertActionRequireForOptimizationIntegration($optimizationIntegration, $extraData);
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @param array $extraData
     */
    private function createAlertActionRequireForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration, $extraData = [])
    {
        /** @var OptimizationRuleInterface $optimizationRule */
        $optimizationRule = $optimizationIntegration->getOptimizationRule();
        $positions = is_array($extraData) && array_key_exists('positions', $extraData) ? $extraData['positions'] : [];

        $detail = [
            'Next action' => 'We need your confirmation before performing any actions. Your platform will not auto optimized with Pubvantage system until you confirm it',
            'Positions' => $positions,
            'note' => "Details of positions with respect to segments can be seen from ad tag management section. Paused and Pinned tags will not be affected after applying changes.",
            "message" => sprintf("The optimization integration rule `%s` (ID:%s) produces new orders that require to confirm", $optimizationIntegration->getName(), $optimizationIntegration->getId())
        ];

        $alert = new Alert();
        $alert
            ->setCode(AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_PENDING)
            ->setOptimizationIntegration($optimizationIntegration)
            ->setDetail($detail)
            ->setPublisher($optimizationRule->getPublisher())
            ->setType(AlertInterface::ALERT_TYPE_ACTION_REQUIRED)
            ->setIsSent(false);

        $this->alertManager->save($alert);
    }
}