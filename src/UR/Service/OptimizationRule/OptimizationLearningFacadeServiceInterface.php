<?php


namespace UR\Service\OptimizationRule;


use UR\Model\Core\OptimizationRuleInterface;

interface OptimizationLearningFacadeServiceInterface
{
    const COMPLETED = 0;
    const UNCOMPLETED = 1;

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function calculateNewScores(OptimizationRuleInterface $optimizationRule);
}