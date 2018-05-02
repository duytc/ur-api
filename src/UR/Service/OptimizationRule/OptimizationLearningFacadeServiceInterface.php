<?php


namespace UR\Service\OptimizationRule;


use UR\Model\Core\OptimizationRuleInterface;

interface OptimizationLearningFacadeServiceInterface
{
    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function calculateNewScores(OptimizationRuleInterface $optimizationRule);
}