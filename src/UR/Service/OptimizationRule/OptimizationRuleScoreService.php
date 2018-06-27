<?php


namespace UR\Service\OptimizationRule;


use DateTime;
use Doctrine\DBAL\Types\Type;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DateUtilInterface;
use UR\Service\DynamicTable\DynamicTableServiceInterface;
use UR\Service\StringUtilTrait;

class OptimizationRuleScoreService implements OptimizationRuleScoreServiceInterface
{
    use StringUtilTrait;

    /**
     * @var DynamicTableServiceInterface
     */
    private $dynamicTableService;

    /**
     * @var OptimizationRuleManagerInterface
     */
    private $optimizationRuleManager;

    /**
     * @var DataTrainingTableServiceInterface
     */
    private $dataTrainingTableService;

    /**
     * OptimizationRuleScoreService constructor.
     * @param DynamicTableServiceInterface $dynamicTableService
     * @param OptimizationRuleManagerInterface $optimizationRuleManager
     * @param DataTrainingTableServiceInterface $dataTrainingTableService
     */
    public function __construct(DynamicTableServiceInterface $dynamicTableService, OptimizationRuleManagerInterface $optimizationRuleManager,
                                DataTrainingTableServiceInterface $dataTrainingTableService)
    {
        $this->dynamicTableService = $dynamicTableService;
        $this->optimizationRuleManager = $optimizationRuleManager;
        $this->dataTrainingTableService = $dataTrainingTableService;
    }

    /**
     * @inheritdoc
     */
    public function createOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);
        $columns = $this->buildColumnsForOptimizationRuleScoreTable($optimizationRule);

        try {
            $table = $this->dynamicTableService->createEmptyTable($tableName, $columns);
        } catch (\Exception $exception) {
            return $exception;
        }

        return $table;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationRuleScoreTableName(OptimizationRuleInterface $optimizationRule)
    {
        $optimizationRuleScoreTableName = sprintf(self::OPTIMIZATION_RULE_SCORE_TABLE_NAME_TEMPLATE, $optimizationRule->getId());

        return $optimizationRuleScoreTableName;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return array
     */
    private function buildColumnsForOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule)
    {
        $columns = [];

        $dateField = $optimizationRule->getDateField();
        $optimizeFields = $this->optimizationRuleManager->getOptimizeFieldName($optimizationRule);

        $columns[$dateField] = Type::DATE;
        $columns[self::IDENTIFIER_KEY] = Type::TEXT;
        $columns[self::SEGMENT_VALUES_KEY] = Type::JSON_ARRAY;

        foreach ($optimizeFields as $fieldName => $type) {
            $columns[$fieldName] = $type;
        }

        $columns[self::SCORE_KEY] = Type::DECIMAL;
        $columns[self::IS_PREDICT_KEY] = Type::BOOLEAN;

        return $columns;
    }

    /**
     * @inheritdoc
     */

    public function getFinalScores(OptimizationRuleInterface $optimizationRule, array $segmentsValues, DateTime $startDate, DateTime $endDate)
    {
        $result = $this->getScoresByDateRange($optimizationRule, $segmentsValues, $startDate, $endDate);

        if (!array_key_exists('columns', $result) || !array_key_exists('rows', $result)) {
            return $result;
        }

        $rows = $result['rows'];
        $rows = $this->removeDuplicatedRows($rows);
        $result['rows'] = $rows;

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getScoresByDateRange(OptimizationRuleInterface $optimizationRule, array $segmentsValues, DateTime $startDate, DateTime $endDate)
    {
        if (!$this->isExistOptimizationRuleScoreTable($optimizationRule)) {
            return [];
        }

        if ($endDate < $startDate) {
            return [];
        }

        $lastDateInHistoricalData = $this->dataTrainingTableService->getLastDateInTrainingDataTable($optimizationRule);

        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);
        $identifiers = $this->getIdentifiersByDateRangeAndSegmentField($segmentsValues, $optimizationRule, $startDate, $endDate);
        $requiredFields = [$optimizationRule->getDateField(), OptimizationRuleInterface::IDENTIFIER_COLUMN];

        //Get historical scores
        if ($lastDateInHistoricalData >= $endDate) {
            $rows = $this->getHistoricalScores($optimizationRule, $segmentsValues, $startDate, $endDate, $identifiers, $tableName);
            $result = $this->makeResult($optimizationRule, $rows, $requiredFields);

            return $result;
        }
        //Get historical scores and predictive score
        if ($lastDateInHistoricalData < $endDate && $lastDateInHistoricalData > $startDate) {
            $historicalScores = $this->getHistoricalScores($optimizationRule, $segmentsValues, $startDate, $lastDateInHistoricalData, $identifiers, $tableName);
            $predictiveScores = $this->getPredictiveScore($optimizationRule, $segmentsValues);
            $predictiveScores = $this->createScoresForRemainFutureDate($optimizationRule, $predictiveScores, $lastDateInHistoricalData, $endDate);

            $rows = array_merge($historicalScores, $predictiveScores);
            $result = $this->makeResult($optimizationRule, $rows, $requiredFields);

            return $result;
        }

        // Get predictive scores
        $predictiveScores = $this->getPredictiveScore($optimizationRule, $segmentsValues);
        $predictiveScores = $this->createScoresForRemainFutureDate($optimizationRule, $predictiveScores, $startDate, $endDate);

        $result = $this->makeResult($optimizationRule, $predictiveScores, $requiredFields);

        return $result;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return \Doctrine\DBAL\Schema\Table|false
     */
    public function isExistOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);

        return $this->dynamicTableService->getTable($tableName);
    }

    /**
     * @param array $segmentsValues
     * @param OptimizationRuleInterface $optimizationRule
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    private function getIdentifiersByDateRangeAndSegmentField(array $segmentsValues, OptimizationRuleInterface $optimizationRule, DateTime $startDate, DateTime $endDate)
    {
        return $this->dataTrainingTableService->getIdentifiersByDateRangeAndSegmentFieldValues($optimizationRule, $segmentsValues, $startDate, $endDate);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segmentsValues
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param $identifiers
     * @param $tableName
     * @return array
     */
    protected function getHistoricalScores(OptimizationRuleInterface $optimizationRule, array $segmentsValues, DateTime $startDate, DateTime $endDate, $identifiers, $tableName): array
    {
        $startDate = $startDate->format(DateUtilInterface::DATE_FORMAT);
        $endDate = $endDate->format(DateUtilInterface::DATE_FORMAT);
        $whereClause = $this->buildWhereClauseToGetHistoricalScore($identifiers, $segmentsValues, $optimizationRule, $startDate, $endDate);
        $rows = $this->dynamicTableService->selectRows($tableName, $whereClause);

        if (empty($rows)) {
            $rows = [];
        }
        //Todo: This is bad solution, need update in next phase
        $filterRows = $this->filterRowsBySegment($rows, $segmentsValues);

        return $filterRows;
    }

    /**
     * @param $identifiers
     * @param array $segmentsValues
     * @param OptimizationRuleInterface $optimizationRule
     * @param $startDate
     * @param $endDate
     * @return string
     */
    private function buildWhereClauseToGetHistoricalScore($identifiers, array $segmentsValues, OptimizationRuleInterface $optimizationRule, $startDate, $endDate)
    {
        $dateField = $optimizationRule->getDateField();
        $identifierClause = '';
        foreach ($identifiers as $identifier) {
            if (empty($identifierClause)) {
                $identifierClause = sprintf(' WHERE (identifier = \'%s\'', $identifier);
            } else {
                $identifierClause = sprintf('%s OR identifier = \'%s\'', $identifierClause, $identifier);
            }
        }

        $whereClause = sprintf('%s) AND ( %s between \'%s\' and \'%s\' ) ORDER BY %s ASC ',
            $identifierClause, $dateField, $startDate, $endDate, $dateField);

        return $whereClause;
    }

    /**
     * @param array $rows
     * @param array $segmentsValues
     * @return array
     */
    private function filterRowsBySegment(array $rows, array $segmentsValues)
    {
        $filterRows = [];

        if (empty($segmentsValues)) {
            foreach ($rows as $row) {
                if (!array_key_exists(OptimizationRuleInterface::IDENTIFIER_COLUMN, $row) || !array_key_exists(OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY, $row)) {
                    continue;
                }
                $identifier = $row[OptimizationRuleInterface::IDENTIFIER_COLUMN];
                $segmentValuesInRow = $row[OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY];
                $segmentValuesInRowArray = json_decode($segmentValuesInRow, true);
                // make sure $segmentValuesInRowArray is an array
                $notGlobalSegments = [];
                if (is_array($segmentValuesInRowArray)) {
                    $notGlobalSegments = array_filter($segmentValuesInRowArray, function ($item) {
                        return $item != OptimizationRuleScoreServiceInterface::GLOBAL_KEY;
                    });
                }
                if (count($notGlobalSegments) == 0) {
                    $filterRows[$identifier] = $row;
                }
            }

            return $filterRows;
        }

        foreach ($rows as $key => $row) {
            $segmentValuesInRow = $row[OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY];
            if (empty($segmentsValues) && ($segmentValuesInRow == 'NULL')) {
                $filterRows[] = $row;
                continue;
            } else {
                if ($segmentValuesInRow == 'NULL') {
                    continue;
                }

                $mapped = true;
                if (!empty($segmentsValues)) {
                    foreach ($segmentsValues as $fieldName => $value) {
                        $fieldName = $this->getStandardName($fieldName);
                        $segmentValuesInRowArray = json_decode($segmentValuesInRow, true);
                        $segmentValuesInRowArray = is_array($segmentValuesInRowArray) ? $segmentValuesInRowArray : [$segmentValuesInRowArray];
                        if (!array_key_exists($fieldName, $segmentValuesInRowArray)) {
                            $mapped = false;
                            continue;
                        }

                        if ($segmentValuesInRowArray[$fieldName] != $value) {
                            $mapped = false;
                        }

                        $remainSegments = array_diff($segmentValuesInRowArray, $segmentsValues);
                        $remainSegments = is_array($remainSegments) ? $remainSegments : [$remainSegments];
                        $notGlobalSegments = array_filter($remainSegments, function ($item) {
                            return $item != OptimizationRuleScoreServiceInterface::GLOBAL_KEY;
                        });
                        if (count($notGlobalSegments) > 0) {
                            $mapped = false;
                        }
                    }

                    if ($mapped) {
                        $filterRows[] = $row;
                    }
                }
            }

        }

        return $filterRows;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $predictiveScores
     * @param $requiredFields
     * @return array
     */
    private function makeResult(OptimizationRuleInterface $optimizationRule, $predictiveScores, $requiredFields)
    {
        if (empty($predictiveScores)) {
            return [];
        }

        $additionalFields = is_array($optimizationRule->getIdentifierFields()) ? $optimizationRule->getIdentifierFields() : [];
        $additionalFields[] = OptimizationRuleInterface::IDENTIFIER_COLUMN;

        $predictiveScores = $this->addMoreFieldsToPredictScores($optimizationRule, $predictiveScores);

        $scores = [];
        foreach ($predictiveScores as $key => $predictiveScore) {
            unset($predictiveScore[DynamicTableServiceInterface::COLUMN_ID]);
            unset($predictiveScore[OptimizationRuleScoreServiceInterface::IS_PREDICT_KEY]);
            unset($predictiveScore[OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY]);

            foreach ($predictiveScore as $fieldName => $value) {
                if (is_numeric($value) && !in_array($fieldName, $additionalFields)) {
                    $predictiveScore[$fieldName] = number_format($value, 4);
                }
            }

            $requiredFields = is_array($requiredFields) ? $requiredFields : [$requiredFields];
            $key = '';
            foreach ($requiredFields as $requiredField) {
                if (!array_key_exists($requiredField, $predictiveScore)) {
                    continue;
                }

                $key = sprintf("%s_%s", $key, $predictiveScore[$requiredField]);
            }

            $scores[$key] = $predictiveScore;
        }

        $result['columns'] = array_keys(reset($scores));
        $result['rows'] = array_values($scores);

        return $result;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $predictiveScores
     * @return mixed
     */
    private function addMoreFieldsToPredictScores(OptimizationRuleInterface $optimizationRule, $predictiveScores)
    {
        $predictiveScores = is_array($predictiveScores) ? $predictiveScores : [$predictiveScores];
        $identifierFields = $optimizationRule->getIdentifierFields();

        if (empty($predictiveScores)) {
            return $predictiveScores;
        }

        $identifierValues = array_map(function ($row) {
            if (is_array($row) && array_key_exists(OptimizationRuleInterface::IDENTIFIER_COLUMN, $row)) {
                return $row[OptimizationRuleInterface::IDENTIFIER_COLUMN];
            }
        }, $predictiveScores);

        $tableName = $this->dataTrainingTableService->getDataTrainingTableName($optimizationRule);
        $whereClause = sprintf(" WHERE %s in ('%s')", OptimizationRuleInterface::IDENTIFIER_COLUMN, implode("','", $identifierValues));
        $fullRows = $this->dynamicTableService->selectRows($tableName, $whereClause);
        $groups = $this->groupRowsByIdentifier($fullRows);

        //Add more field if need, currently display fields on identifiers
        $additionalFields = $identifierFields;

        foreach ($predictiveScores as &$predictiveScore) {
            if (!array_key_exists(OptimizationRuleInterface::IDENTIFIER_COLUMN, $predictiveScore)) {
                continue;
            }

            $identifier = $predictiveScore[OptimizationRuleInterface::IDENTIFIER_COLUMN];

            if (!array_key_exists($identifier, $groups)) {
                continue;
            }

            $row = reset($groups[$identifier]);
            foreach ($additionalFields as $additionalField) {
                if (!array_key_exists($additionalField, $row)) {
                    continue;
                }
                $customFieldName = sprintf("%s%s%s", "", $additionalField, "");
                $predictiveScore[$customFieldName] = $row[$additionalField];
            }
        }

        return $predictiveScores;
    }

    /**
     * @param $rows
     * @return mixed
     */
    private function groupRowsByIdentifier($rows)
    {
        $rows = is_array($rows) ? $rows : [$rows];
        $groups = [];
        foreach ($rows as $row) {
            if (!array_key_exists(OptimizationRuleInterface::IDENTIFIER_COLUMN, $row)) {
                continue;
            }
            $identifier = $row[OptimizationRuleInterface::IDENTIFIER_COLUMN];
            $groups[$identifier][] = $row;
        }

        return $groups;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segmentsValues
     * @return array
     */
    public function getPredictiveScore(OptimizationRuleInterface $optimizationRule, array $segmentsValues)
    {
        if (!$this->isExistOptimizationRuleScoreTable($optimizationRule)) {
            return [];
        }

        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);
        $tomorrow = date_create('tomorrow');
        $identifiers = $this->getIdentifiersByDateRangeAndSegmentField($segmentsValues, $optimizationRule, $tomorrow, $tomorrow);

        $rows = [];
        foreach ($identifiers as $identifier) {
            $whereClause = $this->buildWhereClauseToGetPredictiveScore([$identifier]);
            $originSubRows = $this->dynamicTableService->selectRows($tableName, $whereClause);
            $subRows = $this->filterRowsBySegment($originSubRows, $segmentsValues);

            //Make sure all identifiers have their scores
            if (empty($subRows)) {
                foreach ($originSubRows as $originSubRow) {
                    if (!array_key_exists(OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY, $originSubRow)) {
                        continue;
                    }

                    $segmentsFieldValue = json_decode($originSubRow[OptimizationRuleScoreServiceInterface::SEGMENT_VALUES_KEY], true);
                    $normalSegments = [];
                    if (is_array($segmentsFieldValue)) {
                        $normalSegments = array_filter($segmentsFieldValue, function ($value) {
                            return $value != OptimizationRuleScoreServiceInterface::GLOBAL_KEY;
                        });
                    }

                    if (empty($normalSegments)) {
                        $subRows[] = $originSubRow;
                    }
                }
            }

            $rows = array_merge($rows, $subRows);
        }

        return $rows;
    }

    /**
     * @param $identifiers
     * @return string
     */
    private function buildWhereClauseToGetPredictiveScore($identifiers)
    {
        $identifierClause = '';
        foreach ($identifiers as $identifier) {
            if (empty($identifierClause)) {
                $identifierClause = sprintf(' WHERE (identifier = \'%s\'', $identifier);
            } else {
                $identifierClause = sprintf('%s OR identifier = \'%s\'', $identifierClause, $identifier);
            }
        }

        if (empty($identifierClause)) {
            $whereClause = sprintf(' WHERE is_predict = %d ',
                true);
        } else {
            $whereClause = sprintf('%s) AND is_predict = %d ',
                $identifierClause, true);
        }

        return $whereClause;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $predictiveScores
     * @param DateTime $lastDateInHistoricalData
     * @param DateTime $endDate
     * @return array
     */
    private function createScoresForRemainFutureDate(OptimizationRuleInterface $optimizationRule, $predictiveScores, DateTime $lastDateInHistoricalData, DateTime $endDate)
    {
        $dateField = $optimizationRule->getDateField();

        // normalize dateField by replacing space by dash
        // because we already normalized column name before saving to optimizationRuleScore
        // e.g "date join" => "date_join"
        $dateField = str_replace(' ', '_', $dateField);

        $reports = [];

        $backupDate = $lastDateInHistoricalData;

        foreach ($predictiveScores as $predictiveScore) {
            $lastDateInHistoricalData = $backupDate;

            while ($lastDateInHistoricalData <= $endDate) {
                $nextDate = $lastDateInHistoricalData->format('Y-m-d');
                $copyReport = $predictiveScore;
                $copyReport[$dateField] = $nextDate;
                $reports[] = $copyReport;

                $lastDateInHistoricalData = date('Y-m-d', strtotime($nextDate . ' + 1 days'));
                $lastDateInHistoricalData = date_create_from_format(DateUtilInterface::DATE_FORMAT, $lastDateInHistoricalData)->setTime(0, 0, 0);
            }
        }

        return $reports;
    }

    /**
     * @param $rows
     * @return array
     */
    private function removeDuplicatedRows($rows): array
    {
        $rows = is_array($rows) ? $rows : [$rows];
        $finalRows = array_unique(array_map(function ($row) {
            if (array_key_exists(OptimizationRuleInterface::IDENTIFIER_COLUMN, $row)) {
                unset($row[OptimizationRuleInterface::IDENTIFIER_COLUMN]);
            }
            return $row;
        }, $rows), SORT_REGULAR);

        return array_values($finalRows);
    }
}