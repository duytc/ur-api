<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Worker\Manager;

class Activate3rdPartnerScoringServiceIntegrationListener
{
    private $changeOptimizationIntegrations = [];
    /**
     * @var Manager
     */
    private $manager;

    /**
     * Activate3rdPartnerScoringServiceIntegrationListener constructor.
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
        $optimizationIntegration = $args->getObject();

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return;
        }

        $this->changeOptimizationIntegrations[] = $optimizationIntegration;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $optimizationIntegration = $args->getEntity();

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return;
        }

        if (!$args->hasChangedField('identifierMapping')
            && !$args->hasChangedField('identifierField')
            && !$args->hasChangedField('segments')
            && !$args->hasChangedField('supplies')
            && !$args->hasChangedField('adSlots')
            && !$args->hasChangedField('active')
            && !$args->hasChangedField('optimizationFrequency')
            && !$args->hasChangedField('platformIntegration')
        ) {
            return;
        }

        $this->changeOptimizationIntegrations[] = $optimizationIntegration;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $changeOptimizationIntegrations = $this->changeOptimizationIntegrations;
        $this->changeOptimizationIntegrations = [];

        foreach ($changeOptimizationIntegrations as $changeOptimizationIntegration) {
            if (!$changeOptimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }

            if (!$changeOptimizationIntegration->getOptimizationRule() instanceof OptimizationRuleInterface) {
                continue;
            }

            if (!$changeOptimizationIntegration->isUserConfirm()) {
                continue;
            }

            $this->manager->activateThe3PartnerScoringServiceIntegration($changeOptimizationIntegration->getOptimizationRule()->getId(), $changeOptimizationIntegration->getId());
        }
    }
}