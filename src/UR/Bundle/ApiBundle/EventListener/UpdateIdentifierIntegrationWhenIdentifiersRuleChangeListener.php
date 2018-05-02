<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;

class UpdateIdentifierIntegrationWhenIdentifiersRuleChangeListener
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

        if (!$args->hasChangedField('identifierFields')) {
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

            $identifierFields = $optimizationRule->getIdentifierFields();
            foreach ($optimizationIntegrations as $optimizationIntegration) {
                if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                    continue;
                }
                $identifierField = $optimizationIntegration->getIdentifierField();

                if (!is_array($identifierFields)
                    || empty($identifierFields)
                    || !in_array($identifierField, $identifierFields)
                ) {
                    $identifierField = NULL;

                    $optimizationIntegration->setIdentifierField($identifierField);
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