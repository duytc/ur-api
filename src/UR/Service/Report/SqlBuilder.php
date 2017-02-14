<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use UR\Behaviors\JoinConfigUtilTrait;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\DateRange;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\NumberFilterInterface;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Domain\DTO\Report\Filters\TextFilterInterface;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Service\StringUtilTrait;

class SqlBuilder implements SqlBuilderInterface
{
    use StringUtilTrait;
    use JoinConfigUtilTrait;

    const STATEMENT_KEY = 'statement';
    const DATE_RANGE_KEY = 'dateRange';
    const CONDITION_KEY = 'condition';

    const FIRST_ELEMENT = 0;
    const START_DATE_INDEX = 0;
    const END_DATE_INDEX = 1;
    const DATA_SET_TABLE_NAME_TEMPLATE = '__data_import_%d';

    const JOIN_CONFIG_JOIN_FIELDS = 'joinFields';
    const JOIN_CONFIG_OUTPUT_FIELD = 'outputField';
    const JOIN_CONFIG_FIELD = 'field';
    const JOIN_CONFIG_DATA_SET = 'dataSet';
    const JOIN_CONFIG_DATA_SETS = 'dataSets';

    const JOIN_PARAM_FROM_ALIAS = 'fromAlias';
    const JOIN_PARAM_TO_ALIAS = 'toAlias';
    const JOIN_PARAM_FROM_JOIN_FIELD = 'fromJoinField';
    const JOIN_PARAM_TO_JOIN_FIELD = 'toJoinField';
    const JOIN_PARAM_TO_TABLE_NAME = 'tableName';


    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * SqlBuilder constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->connection = $this->em->getConnection();
    }

    public function buildQueryForSingleDataSet(DataSetInterface $dataSet, $overridingFilters = null)
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $filters = $dataSet->getFilters();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $fields = array_merge($metrics, $dimensions);
        $tableColumns = array_keys($table->getColumns());

        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        if (empty($fields)) {
            throw new InvalidArgumentException('at least one field must be selected');
        }

        $qb = $this->connection->createQueryBuilder();

        foreach ($fields as $field) {
            $qb->addSelect(sprintf('%s as %s_%d', $field, $field, $dataSet->getDataSetId()));
        }

        $qb->from($table->getName());

        if (empty($filters) && empty($overridingFilters)) {
            return array(
                self::DATE_RANGE_KEY => [],
                self::STATEMENT_KEY => $qb->execute()
            );
        }

        $buildResult = $this->buildFilters($filters);
        $conditions = $buildResult[self::CONDITION_KEY];
        $dateRange = $buildResult[self::DATE_RANGE_KEY];
        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            $overridingResult = $this->buildFilters($overridingFilters, null, $dataSet->getDataSetId());
            $conditions = array_merge($conditions, $overridingResult[self::CONDITION_KEY]);
            // override date range
            if (!empty($buildResult[self::DATE_RANGE_KEY])) {
                $dateRange = $overridingResult[self::DATE_RANGE_KEY];
            }
        }

        if (count($conditions) == 1) {
            $qb->where($conditions[self::FIRST_ELEMENT]);

            return array(
                self::DATE_RANGE_KEY => $dateRange,
                self::STATEMENT_KEY => $qb->execute()
            );
        }

        $qb->where(implode(' AND ', $conditions));

        return array(
            self::STATEMENT_KEY => $qb->execute(),
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    /**
     * @inheritdoc
     */
    public function buildQuery(array $dataSets, array $joinConfig, $overridingFilters = null)
    {
        if (empty($dataSets)) {
            throw new InvalidArgumentException('no dataSet');
        }

        if (count($dataSets) == 1) {
            $dataSet = $dataSets[self::FIRST_ELEMENT];
            if (!$dataSet instanceof DataSetInterface) {
                throw new RuntimeException('expect an DataSetInterface object');
            }

            return $this->buildQueryForSingleDataSet($dataSet);
        }

        if (count($joinConfig) < 1) {
            throw new InvalidArgumentException('expect joined field is not empty array when multiple data sets is selected');
        }

        $qb = $this->connection->createQueryBuilder();
        $conditions = [];
        $dateRange = [];

        // add select clause
        $firstJoinBy = null;

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $qb = $this->buildSelectQuery($qb, $dataSet, $dataSetIndex, $joinConfig);
            $buildResult = $this->buildFilters($dataSet->getFilters(), sprintf('t%d', $dataSetIndex));

            $conditions = array_merge($conditions, $buildResult[self::CONDITION_KEY]);
            $dateRange = array_merge($dateRange, $buildResult[self::DATE_RANGE_KEY]);
        }

        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            foreach ($overridingFilters as $filter) {
                if (!$filter instanceof FilterInterface) {
                    continue;
                }

                if ($filter instanceof DateFilterInterface) {
                    $dateRange = new DateRange($filter->getStartDate(), $filter->getEndDate());
                }

                $dataSetId = $this->getDataSetIdInFilter($filter->getFieldName());
                $fieldName = $this->getFieldNameInFilter($filter->getFieldName());
                $filter->setFieldName($fieldName);

                /** @var DataSetInterface $dataSet */
                foreach ($dataSets as $dataSetIndex => $dataSet) {
                    if ((in_array($fieldName, $dataSet->getDimensions()) ||
                        in_array($fieldName, $dataSet->getMetrics())) && $dataSetId == $dataSet->getDataSetId()
                    ) {
                        $conditions[] = $this->buildSingleFilter($filter, sprintf('t%d', $dataSetIndex));//[self::CONDITION_KEY];
                    }
                }
            }
        }

        // add JOIN clause
        $dataSetIds = array_map(function(DataSetInterface $dataSet) {
            return $dataSet->getDataSetId();
        }, $dataSets);

        $qb = $this->buildJoinQuery($qb, $dataSetIds, $joinConfig);

        // add WHERE clause
        if (!empty($conditions)) {
            if (count($conditions) == 1) {
                $qb->where($conditions[self::FIRST_ELEMENT]);
            } else {
                $qb->where(implode(' AND ', $conditions));
            }
        }

        return array(
            self::STATEMENT_KEY => $qb->execute(),
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    protected function getFieldNameInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName,'_');
        $fieldName = substr($filterFieldName, 0, $underScoreCharacter);

        return $fieldName;
    }

    protected function getDataSetIdInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName,'_');
        $dataSetId = substr($filterFieldName, $underScoreCharacter +1, strlen($filterFieldName));

        return $dataSetId;
    }

    /**
     * @param QueryBuilder $qb
     * @param DataSetInterface $dataSet
     * @param $dataSetIndex
     * @param array $joinConfig
     * @return QueryBuilder
     */
    protected function buildSelectQuery(QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex, array $joinConfig)
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $fields = array_merge($metrics, $dimensions);
        $tableColumns = array_keys($table->getColumns());

        // filter all fields that are not in table's columns
        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        // if no field is valid
        if (empty($fields)) {
            throw new InvalidArgumentException('at least one field must be selected');
        }

        // build select query for each data set
        foreach ($fields as $field) {
            $alias = $this->getAliasForField($dataSet->getDataSetId(), $field, $joinConfig);
            $qb->addSelect(sprintf('t%d.%s as %s', $dataSetIndex, $field, $alias));
        }

        return $qb;
    }

    /**
     * Start with the given data set, build JOIN QUERIES with its connected data sets
     *
     * @param QueryBuilder $qb
     * @param $fromDataSetId
     * @param $dataSetIndexes
     * @param array $joinConfig
     * @param array $endDataSets
     * @return QueryBuilder
     */
    private function buildJoinQueryForDataSet(QueryBuilder $qb, $fromDataSetId, $dataSetIndexes, array $joinConfig, array &$endDataSets)
    {
        foreach ($joinConfig as $config) {
            $joinParams = $this->extractJoinQueryParameter($config[self::JOIN_CONFIG_JOIN_FIELDS], $fromDataSetId, $dataSetIndexes, $toDatSetId);
            $endDataSets[] = $toDatSetId;
            if (strpos($joinParams[self::JOIN_PARAM_TO_JOIN_FIELD], ',') !== false) {
                $qb->join(
                    $joinParams[self::JOIN_PARAM_FROM_ALIAS],
                    $joinParams[self::JOIN_PARAM_TO_TABLE_NAME],
                    $joinParams[self::JOIN_PARAM_TO_ALIAS],
                    $this->buildMultipleJoinCondition($joinParams[self::JOIN_PARAM_FROM_JOIN_FIELD], $joinParams[self::JOIN_PARAM_TO_JOIN_FIELD], $joinParams[self::JOIN_PARAM_FROM_ALIAS], $joinParams[self::JOIN_PARAM_TO_ALIAS])
                );
            } else {
                $qb->join(
                    $joinParams[self::JOIN_PARAM_FROM_ALIAS],
                    $joinParams[self::JOIN_PARAM_TO_TABLE_NAME],
                    $joinParams[self::JOIN_PARAM_TO_ALIAS],
                    sprintf('%s.%s = %s.%s', $joinParams[self::JOIN_PARAM_FROM_ALIAS], $joinParams[self::JOIN_PARAM_FROM_JOIN_FIELD], $joinParams[self::JOIN_PARAM_TO_ALIAS], $joinParams[self::JOIN_PARAM_TO_JOIN_FIELD])
                );
            }
        }

        return $qb;
    }

    /**
     * extract fromAlias, toAlias and join fields for a single join query
     * a single join query is something look like
     *
     * INNER JOIN {table_name} {toAlias} ON {fromAlias}.{fromField} = {toAlias}.{toField
     * }
     * @param $joinConfig
     * @param $fromDataSetId
     * @param $dataSetIndexes
     * @param $toDataSetId
     * @return array
     */
    private function extractJoinQueryParameter($joinConfig, $fromDataSetId, $dataSetIndexes, &$toDataSetId)
    {
        $result = [];
        foreach ($joinConfig as $config) {
            $dataSetId = $config[self::JOIN_CONFIG_DATA_SET];
            $field = $config[self::JOIN_CONFIG_FIELD];

            if ($fromDataSetId == $dataSetId) {
                $result[self::JOIN_PARAM_FROM_JOIN_FIELD] = $field;
                $result[self::JOIN_PARAM_FROM_ALIAS] = sprintf('t%d', $dataSetIndexes[$fromDataSetId]);
                continue;
            }

            $table = $this->getDataSetTableSchema($dataSetId);
            $result[self::JOIN_PARAM_TO_ALIAS] = sprintf('t%d', $dataSetIndexes[$dataSetId]);
            $result[self::JOIN_PARAM_TO_JOIN_FIELD] = $field;
            $result[self::JOIN_PARAM_TO_TABLE_NAME] = $table->getName();

            $toDataSetId = $dataSetId;
        }

        return $result;
    }

    /**
     * build JOIN QUERY when there're 2 data set joining together by more than 2 fields
     *
     * @param $fromField
     * @param $field
     * @param $fromAlias
     * @param $alias
     * @return string
     */
    private function buildMultipleJoinCondition($fromField, $field, $fromAlias, $alias)
    {
        $fromFields = explode(',', $fromField);
        $fields = explode(',', $field);
        $conditions = [];
        foreach ($fromFields as $key => $value) {
            $conditions[] = sprintf('%s.%s = %s.%s', $fromAlias, $value, $alias, $fields[$key]);
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @param QueryBuilder $qb
     * @param array $dataSetIds
     * @param array $joinConfig
     * @return QueryBuilder
     */
    protected function buildJoinQuery(QueryBuilder $qb, array $dataSetIds, array $joinConfig)
    {
        $this->normalizeJoinConfig($joinConfig);

        $startDataSets = [];
        $endDataSets = [];
        $dataSetIndexes = array_flip($dataSetIds);
        $startDataSet = current($dataSetIds);

        $table = $this->getDataSetTableSchema($startDataSet);
        $qb->from($table->getName(), sprintf('t%d', $dataSetIndexes[$startDataSet]));

        while (count($startDataSets) <= count($dataSetIds)) {
            if (!in_array($startDataSet, $startDataSets)) {
                $startDataSets[] = $startDataSet;
            }

            $endNodes = $this->findEndNodesForDataSet($joinConfig, $startDataSet, $startDataSets);
            if (empty($endNodes)) {
                $startDataSet = array_shift($endDataSets);
                if ($startDataSet === null) {
                    break;
                }
                continue;
            }

            $qb = $this->buildJoinQueryForDataSet($qb, $startDataSet, $dataSetIndexes, $endNodes, $endDataSets);
            $startDataSet = array_shift($endDataSets);
            if ($startDataSet === null) {
                break;
            }
        }

        return $qb;
    }

    /**
     * @param array $filters
     * @param null $tableAlias
     * @param null $dataSetId
     * @return array
     */
    protected function buildFilters(array $filters, $tableAlias = null, $dataSetId = null)
    {
        $sqlConditions = [];
        $dateRanges = [];
        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($filter instanceof DateFilterInterface) {
                $dateRanges[] = new DateRange($filter->getStartDate(), $filter->getEndDate());
            }

            $sqlConditions[] = $this->buildSingleFilter($filter, $tableAlias, $dataSetId);
        }

        return array(
            self::CONDITION_KEY => $sqlConditions,
            self::DATE_RANGE_KEY => $dateRanges
        );
    }

    /**
     * @param FilterInterface $filter
     * @param null $tableAlias
     * @param null $dataSetId
     * @return string
     */
    protected function buildSingleFilter(FilterInterface $filter, $tableAlias = null, $dataSetId = null)
    {
        if ($dataSetId !== null) {
            $filter->trimTrailingAlias($dataSetId);
        }

        $fieldName = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, $filter->getFieldName()) : $filter->getFieldName();

        if ($filter instanceof DateFilterInterface) {
            if (!$filter->getStartDate() || !$filter->getEndDate()) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN "%s" AND "%s")', $fieldName, $filter->getStartDate(), $filter->getEndDate());
        }

        if ($filter instanceof NumberFilterInterface) {
            $numberFilterComparisonValue = $filter->getComparisonValue();

            switch ($filter->getComparisonType()) {
                case NumberFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s != %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_GREATER:
                    return sprintf('%s > %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_SMALLER:
                    return sprintf('%s < %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                    return sprintf('%s <= %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL:
                    return sprintf('%s >= %.12f', $fieldName, $numberFilterComparisonValue);

                case NumberFilter::COMPARISON_TYPE_IN:
                    $numberFilterInValue = implode(',', $numberFilterComparisonValue);
                    return sprintf('%s IN (%s)', $fieldName, $numberFilterInValue);

                case NumberFilter::COMPARISON_TYPE_NOT_IN:
                    $numberFilterNotInValue = implode(',', $numberFilterComparisonValue);
                    return sprintf('(%s IS NULL OR %s NOT IN (%s))', $fieldName, $fieldName, $numberFilterNotInValue);

                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        if ($filter instanceof TextFilterInterface) {
            $textFilterComparisonValue = $filter->getComparisonValue();

            switch ($filter->getComparisonType()) {
                case TextFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $textFilterComparisonValue);

                case TextFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s != %s', $fieldName, $textFilterComparisonValue);

                case TextFilter::COMPARISON_TYPE_CONTAINS :
                    $contains = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%%%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $contains)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_NOT_CONTAINS :
                    $notContains = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s NOT LIKE \'%%%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s IS NULL OR %s = '' OR %s)", $fieldName, $fieldName, implode(' AND ', $notContains)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_START_WITH:
                    $startWiths = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $startWiths)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_END_WITH:
                    $endWiths = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%%%s\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $endWiths)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_IN:
                    $quotedTextFilterComparisonValue = array_map(function ($tcv) {
                        return sprintf('\'%s\'', $tcv); // add quote to each string value
                    }, $textFilterComparisonValue);

                    $textFilterInValue = implode(',', $quotedTextFilterComparisonValue);
                    return sprintf('%s IN (%s)', $fieldName, $textFilterInValue);

                case TextFilter::COMPARISON_TYPE_NOT_IN:
                    $quotedTextFilterComparisonValue = array_map(function ($tcv) {
                        return sprintf('\'%s\'', $tcv); // add quote to each string value
                    }, $textFilterComparisonValue);

                    $textFilterNotInValue = implode(',', $quotedTextFilterComparisonValue);
                    return sprintf('(%s IS NULL OR %s = \'\' OR %s NOT IN (%s))', $fieldName, $fieldName, $fieldName, $textFilterNotInValue);

                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        throw new InvalidArgumentException(sprintf('filter is not supported'));
    }

    /**
     * @param $dataSetId
     * @return Table
     */
    protected function getDataSetTableSchema($dataSetId)
    {
        $sm = $this->connection->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSetId);

        return $sm->listTableDetails($tableName);
    }
}