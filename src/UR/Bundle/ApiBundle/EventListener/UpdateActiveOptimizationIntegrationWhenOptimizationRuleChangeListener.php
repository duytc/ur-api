<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;

class UpdateActiveOptimizationIntegrationWhenOptimizationRuleChangeListener
{
    protected $changedOptimizationRules;

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $optimizationRule = $args->getEntity();

        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        if (!$args->hasChangedField('reportView')
            && !$args->hasChangedField('dateField')
            && !$args->hasChangedField('dateRange')
            && !$args->hasChangedField('identifierFields')
            && !$args->hasChangedField('optimizeFields')
            && !$args->hasChangedField('segmentFields')
        ) {
            return;
        }

        $this->changedOptimizationRules[] = $optimizationRule;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        if (empty($this->changedOptimizationRules)) {
            return;
        }

        $needToFlush = false;
        foreach ($this->changedOptimizationRules as $optimizationRule) {
            if (!$optimizationRule instanceof OptimizationRuleInterface) {
                return;
            }
            $optimizationIntegrations = $optimizationRule->getOptimizationIntegrations();
            if ($optimizationIntegrations instanceof Collection) {
                $optimizationIntegrations = $optimizationIntegrations->toArray();
            }

            foreach ($optimizationIntegrations as $optimizationIntegration) {
                if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                    continue;
                }

                if ($optimizationIntegration->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION) {
                    $optimizationIntegration->setActive(OptimizationIntegrationInterface::ACTIVE_HAS_NOT_CHANGED);
                    $em->merge($optimizationIntegration);

                    $needToFlush = true;
                }
            }
        }

        $this->changedOptimizationRules = [];

        if ($needToFlush == true) {
            $em->flush();
        }
    }
}