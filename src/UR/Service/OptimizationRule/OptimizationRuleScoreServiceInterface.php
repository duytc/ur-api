<?php


namespace UR\Service\OptimizationRule;


use DateTime;
use DynamicTableServiceInterface;
use UR\Model\Core\OptimizationRuleInterface;

interface OptimizationRuleScoreServiceInterface
{
    const OPTIMIZATION_RULE_SCORE_TABLE_NAME_TEMPLATE = '__optimization_rule_score_%d';
    const SEGMENT_VALUES_KEY = 'segment_field_values';
    const SCORE_KEY = 'score';
    const IS_PREDICT_KEY = 'is_predict';
    const IDENTIFIER_KEY = 'identifier';
    const GLOBAL_KEY = 'global';

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function createOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function getOptimizationRuleScoreTableName(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segments
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    public function getScoresByDateRange(OptimizationRuleInterface $optimizationRule, array $segments, DateTime $startDate, DateTime $endDate);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segmentsValues
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    public function getFinalScores(OptimizationRuleInterface $optimizationRule, array $segmentsValues, DateTime $startDate, DateTime $endDate);
}