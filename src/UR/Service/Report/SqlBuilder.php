<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PDO;
use UR\Behaviors\JoinConfigUtilTrait;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\DateRange;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\NumberFilterInterface;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Domain\DTO\Report\Filters\TextFilterInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\JoinBy\JoinFieldInterface;
use UR\Entity\Core\DataSet;
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
    const JOIN_CONFIG_VISIBLE = 'isVisible';
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

    /**
     * @inheritdoc
     */
    public function buildQueryForSingleDataSet(DataSetInterface $dataSet, $overridingFilters = null)
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $filters = $dataSet->getFilters();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $tableColumns = array_keys($table->getColumns());
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
        /*
         * we get all fields from data set instead of selected fields in report view.
         * Notice: after that, we should filter all fields that is not yet selected.
         * This is important to allow use the none-selected fields in the transformers.
         * If not, the transformers have no value on none-selected fields, so that produce the null value
         */
        $fields = array_keys($dataSetEntity->getAllDimensionMetrics());
        // merge with dimensions, metrics of dataSetDTO because it contains hidden columns such as __date_month, __date_year, ...
        $fields = array_merge($fields, $dimensions, $metrics);
        $fields = array_values(array_unique($fields));

        if (count($tableColumns) < 1) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }

        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        if (empty($fields)) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }

        $qb = $this->connection->createQueryBuilder();

        foreach ($fields as $field) {
            $qb->addSelect(sprintf('%s as %s_%d', $this->connection->quoteIdentifier($field), $field, $dataSet->getDataSetId()));
        }

        $qb->from($this->connection->quoteIdentifier($table->getName()));

        if (empty($filters) && empty($overridingFilters)) {
            $overwriteDateCondition = sprintf('%s IS NULL', $this->connection->quoteIdentifier(\UR\Model\Core\DataSetInterface::OVERWRITE_DATE));
            $qb->where($overwriteDateCondition);
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
            $qb = $this->bindFilterParam($qb, $filters);

            return array(
                self::DATE_RANGE_KEY => $dateRange,
                self::STATEMENT_KEY => $qb->execute()
            );
        }

        $qb->where(implode(' AND ', $conditions));
        $qb = $this->bindFilterParam($qb, $filters);

        return array(
            self::STATEMENT_KEY => $qb->execute(),
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param $filters
     * @param null $dataSetId
     * @return QueryBuilder
     */
    private function bindFilterParam(QueryBuilder $qb, $filters, $dataSetId = null)
    {
        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($filter instanceof DateFilterInterface) {
                $qb->setParameter(sprintf(':startDate%d', $dataSetId ?? 0), $filter->getStartDate(), Type::DATE);
                $qb->setParameter(sprintf(':endDate%d', $dataSetId ?? 0), $filter->getEndDate(), Type::DATE);
            } else if ($filter instanceof TextFilterInterface) {
                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_IN, TextFilter::COMPARISON_TYPE_NOT_IN])) {
                    $bindParamName = sprintf(':%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::SIMPLE_ARRAY);
                } else {
                    $bindParamName = sprintf(':%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::STRING);
                }
            } else if ($filter instanceof NumberFilterInterface) {
                if (in_array($filter->getComparisonType(), [NumberFilter::COMPARISON_TYPE_IN, NumberFilter::COMPARISON_TYPE_NOT_IN])) {
                    $bindParamName = sprintf(':%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::SIMPLE_ARRAY);
                } else {
                    $bindParamName = sprintf(':%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::INTEGER);
                }
            }
        }

        return $qb;
    }

    /**
     * bind params values based on given filters
     *
     * @param Statement $stmt
     * @param $filters
     * @param null $dataSetId
     * @return Statement
     */
    private function bindStatementParam(Statement $stmt, $filters, $dataSetId = null)
    {
        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($filter instanceof DateFilterInterface) {
                $startDate = $filter->getStartDate();
                $endDate = $filter->getEndDate();
                $stmt->bindValue(sprintf('startDate%d', $dataSetId ?? 0), $startDate, PDO::PARAM_STR);
                $stmt->bindValue(sprintf('endDate%d', $dataSetId ?? 0), $endDate, PDO::PARAM_STR);
            } else if ($filter instanceof TextFilterInterface) {
                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_CONTAINS, TextFilter::COMPARISON_TYPE_NOT_CONTAINS, TextFilter::COMPARISON_TYPE_START_WITH, TextFilter::COMPARISON_TYPE_END_WITH])) {
                    continue;
                }

                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_IN, TextFilter::COMPARISON_TYPE_NOT_IN])) {
                    $compareValue = $filter->getComparisonValue();
                    $bindParamName = sprintf('%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $stmt->bindValue($bindParamName, implode(',', $compareValue), PDO::PARAM_STR);
                } else {
                    $compareValue = $filter->getComparisonValue();
                    $bindParamName = sprintf('%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $stmt->bindValue($bindParamName, $compareValue, PDO::PARAM_STR);
                }
            } else if ($filter instanceof NumberFilterInterface) {
                if (in_array($filter->getComparisonType(), [NumberFilter::COMPARISON_TYPE_IN, NumberFilter::COMPARISON_TYPE_NOT_IN])) {
                    $compareValue = $filter->getComparisonValue();
                    $bindParamName = sprintf('%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $stmt->bindValue($bindParamName, implode(',', $compareValue), PDO::PARAM_STR);
                } else {
                    $compareValue = $filter->getComparisonValue();
                    $bindParamName = sprintf('%s%d', $filter->getFieldName(), $dataSetId ?? 0);
                    $stmt->bindValue($bindParamName, $compareValue, PDO::PARAM_INT);
                }
            }
        }

        return $stmt;
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
            $buildResult = $this->buildFilters($dataSet->getFilters(), sprintf('t%d', $dataSetIndex), $dataSet->getDataSetId());

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
                        $conditions[] = $this->buildSingleFilter($filter, sprintf('t%d', $dataSetIndex), $dataSet->getDataSetId());//[self::CONDITION_KEY];
                    }
                }
            }
        }

        // add JOIN clause
        $dataSetIds = array_map(function (DataSetInterface $dataSet) {
            return $dataSet->getDataSetId();
        }, $dataSets);

        $sql = $this->buildJoinQueryForJoinConfig($qb, $dataSetIds, $joinConfig);

        if (count($conditions) == 1) {
            $where = $conditions[self::FIRST_ELEMENT];
        } else {
            $where = implode(' AND ', $conditions);
        }

        $sql = sprintf('%s WHERE (%s)', $sql, $where);
        $stmt = $this->connection->prepare($sql);

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $stmt = $this->bindStatementParam($stmt, $dataSet->getFilters(), $dataSet->getDataSetId());
        }

        $stmt->execute();

        return array(
            self::STATEMENT_KEY => $stmt,
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    /**
     * @param $filterFieldName
     * @return string
     */
    protected function getFieldNameInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName, '_');
        $fieldName = substr($filterFieldName, 0, $underScoreCharacter);

        return $fieldName;
    }

    /**
     * @param $filterFieldName
     * @return string
     */
    protected function getDataSetIdInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName, '_');
        $dataSetId = substr($filterFieldName, $underScoreCharacter + 1, strlen($filterFieldName));

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
        $tableColumns = array_keys($table->getColumns());
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
        /*
         * TODO: this is duplicate code with buildQueryForSingleDataSet()
         * TODO: refactor to use a common function
         * we get all fields from data set instead of selected fields in report view.
         * Notice: after that, we should filter all fields that is not yet selected.
         * This is important to allow use the none-selected fields in the transformers.
         * If not, the transformers have no value on none-selected fields, so that produce the null value
         */
        $fields = array_keys($dataSetEntity->getAllDimensionMetrics());
        // merge with dimensions, metrics of dataSetDTO because it contains hidden columns such as __date_month, __date_year, ...
        $fields = array_merge($fields, $dimensions, $metrics);
        $fields = array_values(array_unique($fields));

        // filter all fields that are not in table's columns
        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        // if no field is valid
        if (empty($fields)) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }

        // build select query for each data set
        foreach ($fields as $field) {
            $alias = $this->getAliasForField($dataSet->getDataSetId(), $field, $joinConfig);
            if ($alias === null) {
                continue;
            }
            $field = $this->connection->quoteIdentifier(sprintf('t%d.%s', $dataSetIndex, $field));
            $qb->addSelect(sprintf('%s as "%s"', $field, $alias));
        }

        return $qb;
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

        $overrideDateField = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, \UR\Model\Core\DataSetInterface::OVERWRITE_DATE) : \UR\Model\Core\DataSetInterface::OVERWRITE_DATE;
        $sqlConditions[] = sprintf('%s IS NULL', $overrideDateField);

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
        $fieldName = $this->connection->quoteIdentifier($fieldName);
        if ($filter instanceof DateFilterInterface) {
            if (!$filter->getStartDate() || !$filter->getEndDate()) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN %s AND %s)', $fieldName, sprintf(':startDate%d', $dataSetId ?? 0), sprintf(':endDate%d', $dataSetId ?? 0));
        }

        $bindParamName = sprintf(':%s%d', $filter->getFieldName(), $dataSetId ?? 0);
        if ($filter instanceof NumberFilterInterface) {
            switch ($filter->getComparisonType()) {
                case NumberFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s <> %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_GREATER:
                    return sprintf('%s > %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_SMALLER:
                    return sprintf('%s < %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                    return sprintf('%s <= %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL:
                    return sprintf('%s >= %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_IN:
                    return sprintf('%s IN (%s)', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_NOT_IN:
                    return sprintf('(%s IS NULL OR %s NOT IN (%s))', $fieldName, $fieldName, $bindParamName);

                case TextFilter::COMPARISON_TYPE_NOT_NULL:
                    return sprintf('(%s IS NOT NULL)', $fieldName);

                case TextFilter::COMPARISON_TYPE_NULL:
                    return sprintf('(%s IS NULL)', $fieldName);

                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        if ($filter instanceof TextFilterInterface) {
            $textFilterComparisonValue = $filter->getComparisonValue();

            switch ($filter->getComparisonType()) {
                case TextFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $bindParamName);

                case TextFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s <> %s', $fieldName, $textFilterComparisonValue);

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
                    return sprintf('%s IN (%s)', $fieldName, $bindParamName);

                case TextFilter::COMPARISON_TYPE_NOT_IN:
                    return sprintf('(%s IS NULL OR %s = \'\' OR %s NOT IN (%s))', $fieldName, $fieldName, $fieldName, $bindParamName);

                case TextFilter::COMPARISON_TYPE_NOT_NULL:
                    return sprintf('(%s IS NOT NULL)', $fieldName);

                case TextFilter::COMPARISON_TYPE_NULL:
                    return sprintf('(%s IS NULL)', $fieldName);

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

    /**
     * @param QueryBuilder $qb
     * @param array $dataSetIds
     * @param array $joinConfig
     * @return string
     */
    protected function buildJoinQueryForJoinConfig(QueryBuilder $qb, array $dataSetIds, array $joinConfig)
    {
        $dataSetIndexes = array_flip($dataSetIds);

        // step1 : randomly select the first 2 data set
        $fromDataSet = current($dataSetIds);
        $table = $this->getDataSetTableSchema($fromDataSet);
        $qb->from($this->connection->quoteIdentifier($table->getName()), sprintf('t%s', $dataSetIndexes[$fromDataSet]));
        $selectQuery = $qb->getSQL();
        $onConditions = [];

        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            $onConditions[] = $this->buildJoinCondition($config, $dataSetIndexes);
        }

        $alias = $this->buildJoinAlias($dataSetIds, $fromDataSet, $dataSetIndexes);
        $joinQuery = sprintf('INNER JOIN (%s) ON (%s)',
            implode(',', $alias),
            implode(' AND ', $onConditions)
        );

        return sprintf('%s %s', $selectQuery, $joinQuery);
    }

    /**
     * @param $dataSetIds
     * @param $fromDataSet
     * @param $dataSetIndexes
     * @return array
     */
    protected function buildJoinAlias($dataSetIds, $fromDataSet, $dataSetIndexes)
    {
        $alias = [];

        foreach ($dataSetIds as $dataSetId) {
            if ($dataSetId == $fromDataSet) {
                continue;
            }

            $table = $this->getDataSetTableSchema($dataSetId);
            $alias[] = sprintf('%s as %s', $this->connection->quoteIdentifier($table->getName()), sprintf('t%s', $dataSetIndexes[$dataSetId]));
        }

        return $alias;
    }

    /**
     * @param JoinConfigInterface $joinConfig
     * @param array $dataSetIndexes
     * @return string
     */
    protected function buildJoinCondition(JoinConfigInterface $joinConfig, array $dataSetIndexes)
    {
        $fromAlias = '';
        $toAlias = '';
        $toJoinField = '';
        $fromJoinField = '';
        $joinFields = $joinConfig->getJoinFields();

        /** @var JoinFieldInterface $joinField */
        foreach ($joinFields as $index => $joinField) {
            $dataSetId = $joinField->getDataSet();
            if ($index == 0) {
                $fromAlias = sprintf('t%s', $dataSetIndexes[$dataSetId]);
                $fromJoinField = $joinField->getField();
                continue;
            }

            $toAlias = sprintf('t%s', $dataSetIndexes[$dataSetId]);
            $toJoinField = $joinField->getField();
        }

        if (strpos($toJoinField, ',') !== false) {
            return $this->buildMultipleJoinCondition($fromJoinField, $toJoinField, $fromAlias, $toAlias);
        }

        $leftCondition = $this->connection->quoteIdentifier(sprintf('%s.%s', $fromAlias, $fromJoinField));
        $rightCondition = $this->connection->quoteIdentifier(sprintf('%s.%s', $toAlias, $toJoinField));
        return sprintf('%s = %s', $leftCondition, $rightCondition);
    }
}