<?php

namespace UR\Worker\Job\Concurrent;

use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Bundle\ApiBundle\EventListener\UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Model\Core\OptimizationIntegrationInterface;

class UpdateOptimizationIntegrationWhenVideoWaterfallTagChangeWorker implements JobInterface
{
    const JOB_NAME = 'synchronizeVideoWaterfallTagWithOptimizationIntegration';

    /** @var OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * UpdateOptimizationIntegrationWhenVideoWaterfallTagChangeWorker constructor.
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
                !array_key_exists(UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::VIDEO_WATERFALL_TAG, $group) ||
                !array_key_exists(UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::OPTIMIZATION_INTEGRATION, $group)
            ) {
                continue;
            }

            $action = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION];
            $videoPublisher = isset($group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::VIDEO_PUBLISHER]) ? $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::VIDEO_PUBLISHER] : null;
            $videoWaterfallTag = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::VIDEO_WATERFALL_TAG];
            $optimizationIntegration = $group[UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::OPTIMIZATION_INTEGRATION];

            $this->syncVideoWaterfallTagByDataFromUR($action, $videoWaterfallTag, $optimizationIntegration, $videoPublisher);
        }
    }

    /**
     * @param string $action
     * @param int $videoWaterfallTag
     * @param int $optimizationIntegrationId
     * @param int $videoPublisher
     */
    private function syncVideoWaterfallTagByDataFromUR($action, $videoWaterfallTag, $optimizationIntegrationId, $videoPublisher)
    {
        $optimizationIntegration = $this->optimizationIntegrationManager->find($optimizationIntegrationId);
        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return;
        }

        $waterfallTags = $optimizationIntegration->getWaterfallTags();
        $waterfallTags = is_array($waterfallTags) ? $waterfallTags : [];

        $videoPublishers = $optimizationIntegration->getVideoPublishers();
        $videoPublishers = is_array($videoPublishers) ? $videoPublishers : [];

        switch ($action) {
            case UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION_ADD:
                if (in_array($videoWaterfallTag, $waterfallTags)) {
                    return;
                }

                $waterfallTags[] = $videoWaterfallTag;
                if (!empty($videoPublisher) && !in_array($videoPublisher, $videoPublishers)) {
                    $videoPublishers[] = $videoPublisher;
                }

                break;

            case UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener::ACTION_REMOVE:
                $waterfallTags = array_filter($waterfallTags, function ($waterfallTagId) use ($videoWaterfallTag) {
                    return $waterfallTagId != $videoWaterfallTag;
                });

                break;
        }

        $optimizationIntegration->setVideoPublishers(array_unique($videoPublishers));
        $optimizationIntegration->setWaterfallTags(array_unique($waterfallTags));
        $this->optimizationIntegrationManager->save($optimizationIntegration);
    }
}