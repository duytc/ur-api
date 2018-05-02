<?php

namespace UR\Service\OptimizationRule;


use DateTime;
use Doctrine\DBAL\Schema\Table;
use Exception;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;

interface DataTrainingTableServiceInterface
{
    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return Table|false
     */
    public function createDataTrainingTable(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function getIdentifiersForOptimizationRule(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $params
     * @return mixed
     */
    public function getSegmentFieldValuesByDateRange(OptimizationRuleInterface $optimizationRule, $params);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $segmentFieldValues
     * @return mixed
     */
    public function getIdentifiersBySegmentsFieldValues(OptimizationRuleInterface $optimizationRule, array $segmentFieldValues);
    /**
     * @param ReportResultInterface $collection
     * @param OptimizationRuleInterface $optimizationRule
     * @return Collection
     * @throws Exception
     */
    public function importDataToDataTrainingTable(ReportResultInterface $collection, OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $identifiers
     * @return ReportResultInterface
     */
    public function getDataByIdentifiers(OptimizationRuleInterface $optimizationRule, $identifiers);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segmentFieldValues
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    public function getIdentifiersByDateRangeAndSegmentFieldValues(OptimizationRuleInterface $optimizationRule, array $segmentFieldValues, DateTime $startDate, DateTime $endDate);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return mixed
     */
    public function getLastDateInTrainingDataTable(OptimizationRuleInterface $optimizationRule);

    public function deleteDataTrainingTable(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return string
     */
    public function getDataTrainingTableName(OptimizationRuleInterface $optimizationRule);

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @param $params
     * @return mixed
     */
    public function getSegmentValuesByAdSlotId(OptimizationIntegrationInterface $optimizationIntegration, $params);
}