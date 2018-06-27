<?php

namespace UR\Worker\Job\Concurrent;

use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Bundle\ApiBundle\EventListener\UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Model\Core\OptimizationIntegrationInterface;

class UpdateOptimizationIntegrationWhenAdSlotChangeWorker implements JobInterface
{
    const JOB_NAME = 'synchronizeAdSlotWithOptimizationIntegration';

    /** @var OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * UpdateOptimizationIntegrationWhenAdSlotChangeWorker constructor.
     * @param OptimizationIntegrationManagerInterface $optimizationIntegrationManager
     */
    public function __construct(OptimizationIntegrationManagerInterface $optimizationIntegrationManager)
    {
        $this->optimizationIntegrationManager = $optimizationIntegrationManager;
    }

    public function run(JobParams $params)
    {
        $actions = $params->getParam('actions');
        $actions = is_array($actions) ? $actions : [$actions];

        foreach ($actions as $group) {
            $group = json_decode(json_encode($group), true);
            if (!array_key_exists(UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION, $group) ||
                !array_key_exists(UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::AD_SLOT, $group) ||
                !array_key_exists(UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::OPTIMIZATION_INTEGRATION, $group)
            ) {
                continue;
            }

            $action = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION];
            $site = isset($group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::SITE]) ? $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::SITE] : null;
            $adSlot = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::AD_SLOT];
            $optimizationIntegration = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::OPTIMIZATION_INTEGRATION];

            $this->syncAdSlotByDataFromUR($action, $adSlot, $optimizationIntegration, $site);
        }
    }

    /**
     * @param $action
     * @param $adSlot
     * @param $optimizationIntegrationId
     * @param $supply
     */
    private function syncAdSlotByDataFromUR($action, $adSlot, $optimizationIntegrationId, $supply)
    {
        $optimizationIntegration = $this->optimizationIntegrationManager->find($optimizationIntegrationId);
        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return;
        }

        $adSlots = $optimizationIntegration->getAdSlots();
        $adSlots = is_array($adSlots) ? $adSlots : [];

        $supplies = $optimizationIntegration->getSupplies();
        $supplies = is_array($supplies) ? $supplies : [];

        switch ($action) {
            case UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION_ADD:
                if (in_array($adSlot, $adSlots)) {
                    return;
                }
                $adSlots[] = $adSlot;
                if (!empty($supply)) {
                    $supplies[] = $supply;
                }
                break;
            case UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION_REMOVE:
                $adSlots = array_filter($adSlots, function ($item) use ($adSlot) {
                    return $item != $adSlot;
                });
                break;
        }

        $optimizationIntegration->setAdSlots(array_unique($adSlots));
        $optimizationIntegration->setSupplies(array_unique($supplies));
        $this->optimizationIntegrationManager->save($optimizationIntegration);
    }
}