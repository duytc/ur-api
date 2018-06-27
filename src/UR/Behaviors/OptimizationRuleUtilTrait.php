<?php

namespace UR\Behaviors;


use DateTime;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\AlertRepositoryInterface;
use UR\Service\OptimizationRule\DataTrainingTableService;

trait OptimizationRuleUtilTrait
{
    public static $OPTIMIZATION_RULE_SCORE_TABLE_NAME_PREFIX_TEMPLATE = '__optimization_rule_score_%d'; // %d is optimization rule id

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return array
     */
    public function getDimensionsMetricsAndTransformField(OptimizationRuleInterface $optimizationRule)
    {
        $reportView = $optimizationRule->getReportView();
        if (!$reportView instanceof ReportViewInterface) {
            return [];
        }

        $fieldNames = $this->getJoinFieldOfDataSets($reportView);

        if(empty($fieldNames)) {
            return $reportView->getFieldTypes();
        }

        $allFields = $reportView->getFieldTypes();
        foreach ($fieldNames as $fieldName) {
            unset($allFields[$fieldName]);
        }

        return $allFields;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    private function getJoinFieldOfDataSets(ReportViewInterface $reportView) {
        $fieldNames = [];

        $jointBys = $reportView->getJoinBy();
        if (empty($jointBys)) {
            return $fieldNames;
        }

        // Remove fields which use to join
        foreach ($jointBys as $jointBy) {
            if (!array_key_exists('joinFields', $jointBy)) {
                continue;
            }
            $joinFields = $jointBy['joinFields'];

            foreach ($joinFields as $joinField) {
                if (!array_key_exists('field', $joinField) || !array_key_exists('dataSet', $joinField)) {
                    continue;
                }
                $fieldNames[] = sprintf('%s_%s', $joinField['field'], $joinField['dataSet']);
            }
        }

        return $fieldNames;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     */
    public function deleteDataTrainingTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getDataTrainingTableName($optimizationRule);

        /** @var DataTrainingTableService $this->dynamicTableService */
        return $this->dynamicTableService->deleteTable($tableName);

    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     */
    public function deleteOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);

        /** @var DataTrainingTableService $this->dynamicTableService */
        return $this->dynamicTableService->deleteTable($tableName);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return string
     */
    public function getOptimizationRuleScoreTableName(OptimizationRuleInterface $optimizationRule)
    {
        return sprintf(self::$OPTIMIZATION_RULE_SCORE_TABLE_NAME_PREFIX_TEMPLATE, $optimizationRule->getId());
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return bool
     */
    public function isOutOfDate(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $frequency = $optimizationIntegration->getOptimizationFrequency();
        $timeStart = $optimizationIntegration->getStartRescoreAt();
        $timeEnd = $optimizationIntegration->getEndRescoreAt();

        if (!$timeStart instanceof DateTime || !$timeEnd instanceof DateTime || empty($frequency)) {
            return true;
        }
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $diff = $now->diff($timeStart);
        $minute = ((int)$diff->format('%a') * 1440) + ((int)$diff->format('%h') * 60) + (int)$diff->format('%i');

        if ($timeEnd < $timeStart) {
            return true;
        }
        switch ($frequency) {
            case DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY:
                return true;
            case DateFilter::DATETIME_DYNAMIC_VALUE_30M:
                if ($minute >= 30) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_1H:
                if ($minute >= 60) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_4H:
                if ($minute >= 240) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_12H:
                if ($minute >= 720) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_24H:
                if ($minute >= 1440) {
                    return true;
                }
                break;
            default:
                break;
        }

        return false;
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @param AlertRepositoryInterface $alertRepository
     * @param string $alertType
     * @return bool
     */
    public function isAlertCreatedInCurrentInterval(OptimizationIntegrationInterface $optimizationIntegration, AlertRepositoryInterface $alertRepository, $alertType = AlertInterface::ALERT_TYPE_ACTION_REQUIRED)
    {
        $startCreatedDate = $this->getCurrentIntervalTime($optimizationIntegration);
        if (!$startCreatedDate instanceof DateTime) {
            return false;
        }
        
        $alertsInThisInterval = $alertRepository->getAlertsCreatedFromDateRange($optimizationIntegration, $alertType, $fromDate = $startCreatedDate, $toDate = date_create('now'));

        return count($alertsInThisInterval);
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return DateTime
     */
    public function getCurrentIntervalTime(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $minutes = $this->getMinutesByInterval($optimizationIntegration->getOptimizationFrequency());
        $dateInterval = new \DateInterval(sprintf('PT%sM', $minutes));
        $diff = $now->sub($dateInterval);

        return $diff;
    }

    /**
     * @param $frequency
     * @return int
     */
    public function getMinutesByInterval($frequency)
    {
        switch ($frequency) {
            case DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY:
                return 60 * 24; //1 day
            case DateFilter::DATETIME_DYNAMIC_VALUE_30M:
                return 30;
            case DateFilter::DATETIME_DYNAMIC_VALUE_1H:
                return 60;
            case DateFilter::DATETIME_DYNAMIC_VALUE_4H:
                return 60 * 4;
            case DateFilter::DATETIME_DYNAMIC_VALUE_12H:
                return 60 * 12;
            case DateFilter::DATETIME_DYNAMIC_VALUE_24H:
                return 60 * 24;
            default:
                break;
        }

        //For empty frequency
        return 0;
    }
}