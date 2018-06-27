<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;
use UR\Service\OptimizationRule\AutomatedOptimization\PubvantageVideo\PubvantageVideoOptimizer;
use UR\Worker\Manager;

/**
 * Class UpdateReportForPlatformIntegrationWhenOptimizationIntegrationChangeListener
 *
 * This listener is for updating resource for Platform integration that relates to the optimize integration
 * e.g: update ad slot for Pubvantage platform, waterfall ad tag for Pubvantage Video platform
 *
 * @package UR\Bundle\ApiBundle\EventListener
 */
class UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener
{
    /* common params */
    const OPTIMIZATION_INTEGRATION = 'optimizationIntegration';
    const ACTION = 'action';
    const ACTION_REMOVE = "Remove";
    const ACTION_ADD = "Add";

    /* Pubvantage platform */
    const SITE = 'site';
    const AD_SLOT = 'adSlot';

    /* Pubvantage video platform */
    const VIDEO_PUBLISHER = 'videoPublisher';
    const VIDEO_WATERFALL_TAG = 'videoWaterfallTag';

    /** @var Manager */
    private $manager;

    /** @var array */
    private $actionsForPlatformIntegrationDisplay = [];

    /** @var array */
    private $actionsForPlatformIntegrationVideo = [];

    /**
     * UpdateResourceForPlatformIntegrationWhenOptimizationIntegrationChangeListener constructor.
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $platformIntegration = $entity->getPlatformIntegration();
        switch ($platformIntegration) {
            case PubvantageOptimizer::PLATFORM_INTEGRATION:
                $this->assignAdSlotToOptimizationIntegration($entity->getId(), $entity->getAdSlots());
                break;

            case PubvantageVideoOptimizer::PLATFORM_INTEGRATION:
                $this->assignVideoWaterfallTagToOptimizationIntegration($entity->getId(), $entity->getWaterfallTags());
                break;
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $platformIntegration = $entity->getPlatformIntegration();
        switch ($platformIntegration) {
            case PubvantageOptimizer::PLATFORM_INTEGRATION:
                if (!$args->hasChangedField('adSlots')) {
                    break;
                }

                $oldAdSlots = $args->getOldValue('adSlots');
                $newAdSlots = $args->getNewValue('adSlots');
                if ($oldAdSlots == $newAdSlots) {
                    break;
                }

                $addAdSlots = array_diff($newAdSlots, $oldAdSlots);
                $removeAdSlots = array_diff($oldAdSlots, $newAdSlots);

                $this->assignAdSlotToOptimizationIntegration($entity->getId(), $addAdSlots);
                $this->removeAdSlotFromOptimizationIntegration($entity->getId(), $removeAdSlots);

                break;

            case PubvantageVideoOptimizer::PLATFORM_INTEGRATION:
                if (!$args->hasChangedField('waterfallTags')) {
                    break;
                }

                $oldWaterfallTags = $args->getOldValue('waterfallTags');
                $newWaterfallTags = $args->getNewValue('waterfallTags');
                if ($oldWaterfallTags == $newWaterfallTags) {
                    break;
                }

                $addWaterfallTags = array_diff($newWaterfallTags, $oldWaterfallTags);
                $removeWaterfallTags = array_diff($oldWaterfallTags, $newWaterfallTags);

                $this->assignVideoWaterfallTagToOptimizationIntegration($entity->getId(), $addWaterfallTags);
                $this->removeVideoWaterfallTagFromOptimizationIntegration($entity->getId(), $removeWaterfallTags);

                break;
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $platformIntegration = $entity->getPlatformIntegration();
        switch ($platformIntegration) {
            case PubvantageOptimizer::PLATFORM_INTEGRATION:
                $this->removeAdSlotFromOptimizationIntegration($entity->getId(), $entity->getAdSlots());
                break;

            case PubvantageVideoOptimizer::PLATFORM_INTEGRATION:
                $this->removeVideoWaterfallTagFromOptimizationIntegration($entity->getId(), $entity->getWaterfallTags());
                break;
        }
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (empty($this->actionsForPlatformIntegrationDisplay) && empty($this->actionsForPlatformIntegrationVideo)) {
            return;
        }

        $actionsForPlatformIntegrationDisplay = $this->actionsForPlatformIntegrationDisplay;
        $this->actionsForPlatformIntegrationDisplay = [];

        $actionsForPlatformIntegrationVideo = $this->actionsForPlatformIntegrationVideo;
        $this->actionsForPlatformIntegrationVideo = [];

        if (!empty($actionsForPlatformIntegrationDisplay)) {
            $this->manager->synchronizeAdSlotWithOptimizationIntegration($actionsForPlatformIntegrationDisplay);
        }

        if (!empty($actionsForPlatformIntegrationVideo)) {
            $this->manager->synchronizeVideoWaterfallTagWithOptimizationIntegration($actionsForPlatformIntegrationVideo);
        }
    }

    /**
     * @param int $optimizationIntegrationId
     * @param array $adSlots
     */
    private function assignAdSlotToOptimizationIntegration($optimizationIntegrationId, array $adSlots)
    {
        if (empty($adSlots)) {
            return;
        }

        $this->actionsForPlatformIntegrationDisplay[] = [
            self::ACTION => self::ACTION_ADD,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::AD_SLOT => $adSlots
        ];
    }

    /**
     * @param int $optimizationIntegrationId
     * @param array $videoWaterfallTags
     */
    private function assignVideoWaterfallTagToOptimizationIntegration($optimizationIntegrationId, array $videoWaterfallTags)
    {
        if (empty($videoWaterfallTags)) {
            return;
        }

        $this->actionsForPlatformIntegrationVideo[] = [
            self::ACTION => self::ACTION_ADD,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::VIDEO_WATERFALL_TAG => $videoWaterfallTags
        ];
    }

    /**
     * @param int $optimizationIntegrationId
     * @param array $adSlots
     */
    private function removeAdSlotFromOptimizationIntegration($optimizationIntegrationId, array $adSlots)
    {
        if (empty($adSlots)) {
            return;
        }

        $this->actionsForPlatformIntegrationDisplay[] = [
            self::ACTION => self::ACTION_REMOVE,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::AD_SLOT => $adSlots
        ];
    }

    /**
     * @param int $optimizationIntegrationId
     * @param array $videoWaterfallTags
     */
    private function removeVideoWaterfallTagFromOptimizationIntegration($optimizationIntegrationId, array $videoWaterfallTags)
    {
        if (empty($videoWaterfallTags)) {
            return;
        }

        $this->actionsForPlatformIntegrationVideo[] = [
            self::ACTION => self::ACTION_REMOVE,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::VIDEO_WATERFALL_TAG => $videoWaterfallTags
        ];
    }
}