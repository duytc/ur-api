<?php

namespace UR\Service\OptimizationRule;


use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DTO\Report\ReportResultInterface;

interface DataTrainingCollectorInterface
{
    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return \UR\Service\DTO\Report\ReportResultInterface
     */
    public function buildDataForOptimizationRule(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $identifiers
     * @return ReportResultInterface
     */
    public function getDataByIdentifiers(OptimizationRuleInterface $optimizationRule, $identifiers);
}