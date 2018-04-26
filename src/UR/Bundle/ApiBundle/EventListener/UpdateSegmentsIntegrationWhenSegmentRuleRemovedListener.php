<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use Doctrine\Common\Collections\Collection;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;

class UpdateSegmentsIntegrationWhenSegmentRuleRemovedListener
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

        if (!$args->hasChangedField(PubvantageOptimizer::REFRESH_CACHE_SEGMENT_FIELDS_KEY)) {
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
                $segments_mapping = $optimizationIntegration->getSegments();
                foreach ($segments_mapping as $key => $segment) {
                    if (!is_array($segment) || !array_key_exists(PubvantageOptimizer::TO_FACTOR_KEY, $segment)) {
                        continue;
                    }
                    if (!in_array($segment[PubvantageOptimizer::TO_FACTOR_KEY], $optimizationRule->getSegmentFields())) {
                        unset($segments_mapping[$key]);
                    }
                }

                $optimizationIntegration->setSegments(array_values($segments_mapping));
                $em->merge($optimizationIntegration);
            }
        }

        $this->changedOptimizationRules = [];

        $em->flush();
    }
}