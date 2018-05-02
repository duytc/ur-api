<?php


namespace UR\Service\OptimizationRule\AutomatedOptimization;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\PublicSimpleException;

interface AutomatedOptimizerInterface
{
    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return OptimizerInterface
     * @throws PublicSimpleException
     */
    public function getOptimizer(OptimizationIntegrationInterface $optimizationIntegration);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param null $optimizationIntegrationIds
     * @return mixed
     */
    public function optimizeForRule(OptimizationRuleInterface $optimizationRule, $optimizationIntegrationIds = null);
}