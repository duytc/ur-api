<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
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

    const STATEMENT_KEY = 'statement';
    const DATE_RANGE_KEY = 'dateRange';
    const CONDITION_KEY = 'condition';

    const FIRST_ELEMENT = 0;
    const START_DATE_INDEX = 0;
    const END_DATE_INDEX = 1;
    const DATA_SET_TABLE_NAME_TEMPLATE = '__data_import_%d';

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
    public function buildQuery(array $dataSets, array $joinByConfig, $overridingFilters = null)
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

        if (count($joinByConfig) < 1) {
            throw new InvalidArgumentException('expect joined field is not empty array when multiple data sets is selected');
        }

        $qb = $this->connection->createQueryBuilder();
        $conditions = [];
        $dateRange = [];

        // add select clause
        $firstJoinBy = null;

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $qb = $this->buildSelectQuery($qb, $dataSet, $dataSetIndex, $joinByConfig, $firstJoinBy);
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
     * @param array $joinByConfig
     * @param null|string $firstJoinBy first join by that is used for join table, details: t0 join tI on t0.firstJoinBy = tI.joinField.
     * firstJoinBy by default = null and will be changed to a valid value after first element selecting (FIRST_ELEMENT)
     * @return QueryBuilder
     */
    protected function buildSelectQuery(QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex, array $joinByConfig, &$firstJoinBy)
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $fields = array_merge($metrics, $dimensions);
        $tableColumns = array_keys($table->getColumns());

        // current support only one join field. TODO: support more join fields
        if (count($joinByConfig) < 1 || !array_key_exists('joinFields', $joinByConfig[0]) || !array_key_exists('outputField', $joinByConfig[0])) {
            throw new InvalidArgumentException('at least one join field must be selected');
        }

        $joinFields = $joinByConfig[0]['joinFields'];
        $flattenJoinFields = [];
        $outputJoinField = $joinByConfig[0]['outputField'];
        $joinBy = false;

        foreach ($joinFields as $joinField) {
            if (!array_key_exists('dataSet', $joinField) || !array_key_exists('field', $joinField)) {
                throw new InvalidArgumentException('joinBy is an invalid json');
            }

            if ($joinField['dataSet'] === $dataSet->getDataSetId()) {
                $joinBy = $joinField['field'];
                break;
            }
        }

        $flattenJoinFields = array_map(function ($joinField) {
            return (!array_key_exists('field', $joinField) || !array_key_exists('field', $joinField))
                ? ''
                : sprintf('%s_%d', $joinField['field'], $joinField['dataSet']);
        }, $joinFields);

        if ($joinBy === false) {
            throw new InvalidArgumentException(sprintf('could find valid join field to match dataSet %d and joinByConfig', $dataSet->getDataSetId()));
        }

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
            $fieldWithDataSetId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            if (in_array($fieldWithDataSetId, $flattenJoinFields)) {
                continue;
            }

            $qb->addSelect(sprintf('t%d.%s as %s_%d', $dataSetIndex, $field, $field, $dataSet->getDataSetId()));
        }

        // build select query for joinFields
        //$qb->addSelect(sprintf('t%d.%s as %s', $dataSetIndex, $joinBy, $outputJoinField));

        if ($dataSetIndex === self::FIRST_ELEMENT) {
            // build select query for joinFields
            $qb->addSelect(sprintf('t%d.%s as \'%s\'', $dataSetIndex, $joinBy, $outputJoinField));
            $qb->from($table->getName(), sprintf('t%d', $dataSetIndex));

            // update firstJoinBy
            $firstJoinBy = $joinBy;
        } else {
            $qb->join('t0', $table->getName(), sprintf('t%d', $dataSetIndex), sprintf('t0.%s = t%d.%s', $firstJoinBy, $dataSetIndex, $joinBy));
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