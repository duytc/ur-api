<?php

namespace UR\Service\OptimizationRule;

use DateTime;
use Doctrine\DBAL\Schema\Table;
use Exception;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DateUtilInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\DynamicTable\DynamicTableServiceInterface;
use UR\Service\RestClientTrait;
use UR\Service\StringUtilTrait;

class DataTrainingTableService implements DataTrainingTableServiceInterface
{
    use OptimizationRuleUtilTrait;
    use  RestClientTrait;
    use StringUtilTrait;

    const DATA_TRAINING_TABLE_NAME_PREFIX_TEMPLATE = '__data_training_%d'; // %d is optimization rule id

    /**
     * @var DynamicTableServiceInterface
     */
    private $dynamicTableService;

    /** @var \Doctrine\DBAL\Connection */
    private $conn;

    /**
     * DataTrainingTableService constructor.
     * @param DynamicTableServiceInterface $dynamicTableService
     */
    public function __construct(DynamicTableServiceInterface $dynamicTableService)
    {
        $this->dynamicTableService = $dynamicTableService;
        $this->conn = $dynamicTableService->getConn();
    }

    /**
     * @inheritdoc
     */
    public function importDataToDataTrainingTable(ReportResultInterface $collection, OptimizationRuleInterface $optimizationRule)
    {
        $reportView = $optimizationRule->getReportView();
        if (!$reportView instanceof ReportViewInterface) {
            return $collection;
        }

        //create or get dataSet table
        $table = $this->createDataTrainingTable($optimizationRule);
        if (!$table instanceof Table) {
            return $collection;
        }

        $tableName = $table->getName();

        try {
            $dimensions = $reportView->getDimensions();
            $rows = $collection->getRows();
            $columns = $collection->getColumns();

            foreach ($columns as $k => $column) {
                if (preg_match('/\s/', $k)) {
                    $newKey = str_replace(' ', '_', $k);
                    unset($columns[$k]);
                    $columns[$newKey] = $column;
                }
            }

            if ($rows->count() < 1) {
                return true;
            }

            $insertValues = array();
            $questionMarks = [];
            $uniqueIds = [];
            $preparedInsertCount = 0;
            $setOrderColumns = false;
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach ($row as $key => $value) {
                    if (preg_match('/\s/', $key)) {
                        $newKey = str_replace(' ', '_', $key);
                        unset($row[$key]);
                        $row[$newKey] = $value;
                    }
                }

                if ($setOrderColumns == false) {
                    $columns = array_intersect_key($row, $columns);
                    $setOrderColumns = true;
                }

                $uniqueKeys = array_intersect_key($row, $dimensions);
                $uniqueKeys = array_filter($uniqueKeys, function ($value) {
                    return $value !== null;
                });

                $uniqueId = md5(implode(":", $uniqueKeys));
                //update
                $uniqueIds[] = $uniqueId;
                $questionMarks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
                $insertValues = array_merge($insertValues, array_values($row));
                $preparedInsertCount++;
                if ($preparedInsertCount === $this->dynamicTableService->getBatchSize()) {
                    $this->dynamicTableService->insertDataToTable($tableName, $columns, $questionMarks, $insertValues);
                    $insertValues = [];
                    $questionMarks = [];
                    $preparedInsertCount = 0;
                }
            }

            if ($preparedInsertCount > 0 && is_array($columns) && is_array($questionMarks)) {
                $this->dynamicTableService->insertDataToTable($tableName, $columns, $questionMarks, $insertValues);
            }

            return new Collection($columns, $rows);
        } catch (Exception $exception) {
            $this->dynamicTableService->rollBack();
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        } finally {
            $this->dynamicTableService->clear();
            gc_collect_cycles();
        }
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return Table|false
     *
     */
    public function getDataTrainingTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getDataTrainingTableName($optimizationRule);

        return $this->dynamicTableService->getTable($tableName);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return string
     */
    public function getDataTrainingTableName(OptimizationRuleInterface $optimizationRule)
    {
        return sprintf(self::DATA_TRAINING_TABLE_NAME_PREFIX_TEMPLATE, $optimizationRule->getId());
    }

    /**
     * @param $text
     * @param int $count
     * @param string $separator
     * @return string
     */
    private function placeholders($text, $count = 0, $separator = ",")
    {
        $result = array();
        if ($count > 0) {
            for ($x = 0; $x < $count; $x++) {
                $result[] = $text;
            }
        }

        return implode($separator, $result);
    }

    /**
     * @inheritdoc
     */
    public function getIdentifiersBySegmentsFieldValues(OptimizationRuleInterface $optimizationRule, array $segmentFieldValues)
    {
        //create or get dataSet table
        $table = $this->getDataTrainingTable($optimizationRule);
        if (!$table instanceof Table) {
            return [];
        }

        $tableName = $table->getName();
        foreach ($segmentFieldValues as $field => $value) {
            if (!$table->hasColumn($field)) {
                throw  new InvalidArgumentException(sprintf('Table %s has no field %s', $tableName, $field));
            }
        }

        $whereClause = $this->buildWhereClauseToGetIdentifierBySegment($segmentFieldValues);
        $columnName = OptimizationRuleInterface::IDENTIFIER_COLUMN;

        return $this->dynamicTableService->selectDistinctOneColumns($tableName, $columnName, $whereClause);
    }

    /**
     * @param array $segmentFieldValues
     * @return string
     */
    private function buildWhereClauseToGetIdentifierBySegment(array $segmentFieldValues)
    {
        $whereClause = '';

        foreach ($segmentFieldValues as $fieldName => $value) {
            if (empty($whereClause)) {
                $whereClause = sprintf('WHERE %s =\'%s\'', $fieldName, $value);
            } else {
                $whereClause = sprintf('%s AND %s =\'%s\'', $whereClause, $fieldName, $value);
            }
        }

        return $whereClause;
    }


    /**
     * @inheritdoc
     */
    public function getIdentifiersForOptimizationRule(OptimizationRuleInterface $optimizationRule)
    {
        //create or get dataSet table
        $table = $this->getDataTrainingTable($optimizationRule);
        if (!$table instanceof Table) {
            return [];
        }

        $tableName = $table->getName();
        $columnName = OptimizationRuleInterface::IDENTIFIER_COLUMN;

        return $this->dynamicTableService->selectDistinctOneColumns($tableName, $columnName);
    }


    /**
     * @inheritdoc
     */
    public function getSegmentFieldValuesByDateRange(OptimizationRuleInterface $optimizationRule, $params)
    {
        $startDate = isset($params['startDate']) ? $params['startDate'] : "";
        $endDate = isset($params['endDate']) ? $params['endDate'] : "";

        $tableName = $this->getDataTrainingTableName($optimizationRule);

        $lastDateInHistory = $this->getLastDateInTrainingDataTable($optimizationRule);
        $startDateObject = date_create_from_format(DateUtilInterface::DATE_FORMAT, $startDate);
        $endDateObject = date_create_from_format(DateUtilInterface::DATE_FORMAT, $endDate);
        $hasFutureDateInDateRange = ($startDateObject > $lastDateInHistory) || ($endDateObject > $lastDateInHistory);


        if (empty($startDate) || empty($endDate) || $hasFutureDateInDateRange) {
            $whereClause = null;
        } else {
            $whereClause = $this->buildWhereClauseToGetSegmentFieldValuesInDateRange($optimizationRule, $startDate, $endDate);
        }

        $segmentFields = $optimizationRule->getSegmentFields();

        $result = [];
        foreach ($segmentFields as $segmentField) {
            $columnNameInDataBase = $this->getStandardName($segmentField);
            $resultTemporary = $this->dynamicTableService->selectDistinctOneColumns($tableName, $columnNameInDataBase, $whereClause);

            // remove value is global from result
            foreach ($resultTemporary as $key => $value) {
                if (OptimizationRuleScoreServiceInterface::GLOBAL_KEY == $value) {
                    unset($resultTemporary[$key]);
                    break;
                }
            }

            $result[$segmentField] = array_values($resultTemporary);
        }

        return $result;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool|mixed
     */
    public function getLastDateInTrainingDataTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getDataTrainingTableName($optimizationRule);
        $dataField = $optimizationRule->getDateField();
        if (empty($dataField)) {
            return false;
        }

        $dataField =  $this->getStandardName($dataField);
        $readDateRange = $this->dynamicTableService->getAllValuesOfOneColumn($tableName, $dataField);
        if (empty($readDateRange) || !is_array($readDateRange)) {
            return false;
        }

        $dateRange = array_map(function ($dateString) use ($readDateRange) {
            return date_create_from_format(DateUtilInterface::DATE_FORMAT, $dateString);
        }, $readDateRange);

        $lastDate = max($dateRange);

        return $lastDate;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $startDate
     * @param $endDate
     * @return string
     */
    private function buildWhereClauseToGetSegmentFieldValuesInDateRange(OptimizationRuleInterface $optimizationRule, $startDate, $endDate)
    {
        $dateField = $optimizationRule->getDateField();
        $whereClause = sprintf(' WHERE (%s between \'%s\' and \'%s\' ) ', $dateField, $startDate, $endDate);

        return $whereClause;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifiersByDateRangeAndSegmentFieldValues(OptimizationRuleInterface $optimizationRule, array $segmentFieldValues, DateTime $startDate, DateTime $endDate)
    {
        //create or get dataSet table
        $table = $this->getDataTrainingTable($optimizationRule);
        if (!$table instanceof Table) {
            return [];
        }

        $tableName = $table->getName();
        foreach ($segmentFieldValues as $fieldName => $value) {
            $standFieldName = $this->getStandardName($fieldName);
            if (!$table->hasColumn($standFieldName)) {
                throw new Exception(sprintf('Table %s has no column %s', $standFieldName, $value));
            }

            $segmentFieldValues[$standFieldName] = $value;
            unset($segmentFieldValues[$fieldName]);
        }

        $whereClause = $this->buildWhereClauseToGetIdentifiersByDateRangeAndSegmentFieldValues($optimizationRule, $segmentFieldValues, $startDate, $endDate);
        $columnName = OptimizationRuleInterface::IDENTIFIER_COLUMN;

        return $this->dynamicTableService->selectDistinctOneColumns($tableName, $columnName, $whereClause);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $segmentFieldValues
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return string
     */
    private function buildWhereClauseToGetIdentifiersByDateRangeAndSegmentFieldValues(OptimizationRuleInterface $optimizationRule, array $segmentFieldValues, DateTime $startDate, DateTime $endDate)
    {
        $whereClause = '';

        foreach ($segmentFieldValues as $fieldName => $value) {
            if (empty($whereClause)) {
                $whereClause = sprintf('WHERE %s =\'%s\'', $fieldName, $value);
            } else {
                $whereClause = sprintf('%s AND %s =\'%s\'', $whereClause, $fieldName, $value);
            }
        }


        $dateField = $optimizationRule->getDateField();
        if (!empty($whereClause)) {
            $whereClause = sprintf(' %s AND (%s between \'%s\' and \'%s\' ) ', $whereClause, $dateField, $startDate->format(DateUtilInterface::DATE_FORMAT), $endDate->format(DateUtilInterface::DATE_FORMAT));
        } else {
            $whereClause = sprintf(' WHERE (%s between \'%s\' and \'%s\' ) ', $dateField, $startDate->format(DateUtilInterface::DATE_FORMAT), $endDate->format(DateUtilInterface::DATE_FORMAT));
        }

        $lastDateInHistorical = $this->getLastDateInTrainingDataTable($optimizationRule);
        if ($startDate > $lastDateInHistorical || $endDate > $lastDateInHistorical) {
            $whereClause = '';
        }

        return $whereClause;
    }

    /**
     * @inheritdoc
     */
    public function getDataByIdentifiers(OptimizationRuleInterface $optimizationRule, $identifiers)
    {
        $reportView = $optimizationRule->getReportView();
        if (!$reportView instanceof ReportViewInterface) {
            return [];
        }

        $rows = new \SplDoublyLinkedList();

        $table = $this->createDataTrainingTable($optimizationRule);
        if (!$table instanceof Table) {
            return $rows;
        }

        $qb = $this->conn->createQueryBuilder();
        $qb
            ->select("*")
            ->from($table->getName(), "a");

        if (!empty($identifiers)) {
            $identifiers = is_array($identifiers) ? $identifiers : [$identifiers];
            $qb
                ->andWhere(sprintf('a.%s IN ("%s")', OptimizationRuleInterface::IDENTIFIER_COLUMN, implode('","', $identifiers)));
        }

        try {
            $stmt = $qb->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (empty($row) || !is_array($row)) {
                    continue;
                }

                $rows->push($row);
            }
        } catch (Exception $e) {

        }

        $fieldTypes = $reportView->getFieldTypes();
        $columns = array_keys($fieldTypes);
        $columns = array_combine($columns, $columns);

        $reportResult = new ReportResult($rows, [], [], []);
        $reportResult->setColumns($columns);
        $reportResult->setTypes($fieldTypes);
        $reportResult->setTotalReport($rows->count());

        $reportResult->generateReports();

        return $reportResult;
    }

    /**
     * @inheritdoc
     */
    public function createDataTrainingTable(OptimizationRuleInterface $optimizationRule)
    {
        $dataTrainingTableName = $this->getDataTrainingTableName($optimizationRule);
        $dataTrainingTableColumns = $this->buildTrainingDataTableColumns($optimizationRule);

        return $this->dynamicTableService->createEmptyTable($dataTrainingTableName, $dataTrainingTableColumns);

    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return array
     */
    private function buildTrainingDataTableColumns(OptimizationRuleInterface $optimizationRule)
    {
        // get all columns
        $dimensionsMetricsAndTransformField = $this->getDimensionsMetricsAndTransformField($optimizationRule);
        $dimensionsMetricsAndTransformField[OptimizationRuleInterface::IDENTIFIER_COLUMN] = FieldType::TEXT;

        return $dimensionsMetricsAndTransformField;
    }

    /**
     * @inheritdoc
     */
    public function getSegmentValuesByAdSlotId(OptimizationIntegrationInterface $optimizationIntegration, $params)
    {
        $searchKey = isset($params['searchKey']) ? $params['searchKey'] : "";
        $segmentKey = isset($params['segment']) ? $params['segment'] : "";

        $tableName = $this->getDataTrainingTableName($optimizationIntegration->getOptimizationRule());

        foreach ($optimizationIntegration->getSegments() as $segment) {
            if (in_array($segmentKey, $segment)) {
                $columnName = $segment['toFactor'];
            }
        }

        if (!isset($columnName)) {
            return [];
        }

        $whereClause = '';
        if (!empty($searchKey) && isset($columnName)) {
            $whereClause = 'WHERE ' .$columnName. ' LIKE "%'.$searchKey.'%"';
        }
        $result = $this->dynamicTableService->selectDistinctOneColumns($tableName, $columnName, $whereClause);

        return $result;
    }
}