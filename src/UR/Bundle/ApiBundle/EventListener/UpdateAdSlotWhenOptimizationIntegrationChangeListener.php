<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Worker\Manager;

class UpdateAdSlotWhenOptimizationIntegrationChangeListener
{
    const AD_SLOT = 'adSlot';
    const SITE = 'site';
    const OPTIMIZATION_INTEGRATION = 'optimizationIntegration';
    const ACTION = 'action';

    const ACTION_REMOVE = "Remove";
    const ACTION_ADD = "Add";

    /** @var Manager */
    private $manager;

    private $actions = [];

    /**
     * UpdateAdSlotWhenOptimizationIntegrationChangeListener constructor.
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
        $entity = $args->getObject();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $this->assignAdSlotToOptimizationIntegration($entity->getId(), $entity->getAdSlots());
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $oldAdSlots = $entity->getAdSlots();
        $newAdSlots = $entity->getAdSlots();

        if ($args->hasChangedField('adSlots')) {
            $oldAdSlots = $args->getOldValue('adSlots');
            $newAdSlots = $args->getNewValue('adSlots');
        }

        if ($oldAdSlots == $newAdSlots) {
            return;
        }

        $removeAdSlots = array_diff($oldAdSlots, $newAdSlots);
        $addAdSlots = array_diff($newAdSlots, $oldAdSlots);

        $this->assignAdSlotToOptimizationIntegration($entity->getId(), $addAdSlots);
        $this->removeAdSlotFromOptimizationIntegration($entity->getId(), $removeAdSlots);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof OptimizationIntegrationInterface) {
            return;
        }

        $this->removeAdSlotFromOptimizationIntegration($entity->getId(), $entity->getAdSlots());
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (empty($this->actions)) {
            return;
        }

        $actions = $this->actions;
        $this->actions = [];

        $this->manager->synchronizeAdSlotWithOptimizationIntegration($actions);
    }

    /**
     * @param $optimizationIntegrationId
     * @param $adSlots
     */
    private function assignAdSlotToOptimizationIntegration($optimizationIntegrationId, $adSlots)
    {
        if (empty($adSlots)) {
            return;
        }

        $this->actions[] = [
            self::ACTION => self::ACTION_ADD,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::AD_SLOT => $adSlots
        ];
    }

    /**
     * @param $optimizationIntegrationId
     * @param $adSlots
     */
    private function removeAdSlotFromOptimizationIntegration($optimizationIntegrationId, $adSlots)
    {
        if (empty($adSlots)) {
            return;
        }

        $this->actions[] = [
            self::ACTION => self::ACTION_REMOVE,
            self::OPTIMIZATION_INTEGRATION => $optimizationIntegrationId,
            self::AD_SLOT => $adSlots
        ];
    }
}