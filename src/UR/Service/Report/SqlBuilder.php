<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
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

        foreach($fields as $index=>$field) {
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

        if (empty($filters)) {
            return $qb->execute();
        }

        $conditions = $this->buildFilters($filters);
        if (is_array($overridingFilters)) {
            $overridingConditions = $this->buildFilters($overridingFilters);
            $conditions = array_merge($conditions, $overridingConditions);
        }

        if (count($conditions) == 1) {
            $qb->where($conditions[self::FIRST_ELEMENT]);
            return $qb->execute();
        }

        $qb->where(implode(' AND ', $conditions));
        return $qb->execute();
    }


    public function buildQuery(array $dataSets, $joinedField, $overridingFilters = null)
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

        if ($joinedField === null) {
            throw new InvalidArgumentException('joined field is required when multiple data sets is selected');
        }

        $qb = $this->connection->createQueryBuilder();
        $conditions = [];

        // add select clause
        /** @var DataSetInterface $dataSet */
        foreach($dataSets as $dataSetIndex=>$dataSet) {
            $qb = $this->buildSelectQuery($qb, $dataSet, $dataSetIndex, $joinedField);
            $conditions = array_merge($conditions, $this->buildFilters($dataSet->getFilters(), sprintf('t%d', $dataSetIndex)));
        }

        if (is_array($overridingFilters)) {
            $overridingConditions = $this->buildFilters($overridingFilters);
            $conditions = array_merge($conditions, $overridingConditions);
        }

        // add WHERE clause
        if (!empty($conditions)) {
            if (count($conditions) == 1) {
                $qb->where($conditions[self::FIRST_ELEMENT]);
            } else {
                $qb->where(implode(' AND ', $conditions));
            }
        }

        return $qb->execute();
    }

    protected function buildSelectQuery(QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex, $joinBy)
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $fields = array_merge($metrics, $dimensions);
        $tableColumns = array_keys($table->getColumns());
        foreach($fields as $index=>$field) {

            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        if (empty($fields)) {
            throw new InvalidArgumentException('at least one field must be selected');
        }

        foreach ($fields as $field) {
            if ($joinBy === $this->removeIdSuffix($field)) {
                continue;
            }
            $qb->addSelect(sprintf('t%d.%s as %s_%d', $dataSetIndex, $field, $field, $dataSet->getDataSetId()));
        }

        $qb->addSelect(sprintf('t%d.%s as %s', $dataSetIndex, $joinBy, $joinBy));

        if ($dataSetIndex === self::FIRST_ELEMENT) {
            $qb->from($table->getName(), sprintf('t%d', $dataSetIndex));
        } else {
            $qb->join('t0', $table->getName(), sprintf('t%d', $dataSetIndex), sprintf('t0.%s = t%d.%s', $joinBy, $dataSetIndex, $joinBy));
        }

        return $qb;
    }

    /**
     * @param array $filters
     * @param null $tableAlias
     * @return array
     */
    protected function buildFilters(array $filters, $tableAlias = null)
    {
        $sqlConditions = [];

        foreach($filters as $filter) {
            if (!$filter instanceof AbstractFilterInterface) {
                continue;
            }

            $sqlConditions[] = $this->buildSingleFilter($filter, $tableAlias);
        }

        return $sqlConditions;
    }

    /**
     * @param AbstractFilterInterface $filter
     * @param null $tableAlias
     * @return string
     */
    protected function buildSingleFilter(AbstractFilterInterface $filter, $tableAlias = null)
    {
        $fieldName = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, $filter->getFieldName()) : $filter->getFieldName();

        if ($filter instanceof DateFilterInterface) {
            if (!$filter->getStartDate() || !$filter->getEndDate()) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN "%s" AND "%s")', $fieldName, $filter->getStartDate(), $filter->getEndDate());
        }

        if ($filter instanceof NumberFilterInterface) {
            switch ($filter->getComparisonType()) {
                case NumberFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s != %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_GREATER:
                    return sprintf('%s > %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_SMALLER:
                    return sprintf('%s < %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                    return sprintf('%s <= %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL:
                    return sprintf('%s >= %f', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_IN:
                    return sprintf('%s in (%s)', $fieldName, $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_NOT_IN:
                    return sprintf('%s not in (%s)', $fieldName, $filter->getComparisonValue());
                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        if ($filter instanceof TextFilterInterface) {
            switch ($filter->getComparisonType()) {
                case TextFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s != %s', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_CONTAINS :
                    return sprintf('%s LIKE \'%%%s%%\'', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_NOT_CONTAINS :
                    return sprintf('%s NOT LIKE \'%%%s%%\'', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_START_WITH:
                    return sprintf('%s LIKE \'%s%%\'', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_END_WITH:
                    return sprintf('%s LIKE \'%%%s\'', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_IN:
                    return sprintf('%s IN (%s)', $fieldName, $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_NOT:
                    return sprintf('%s NOT IN (%s)', $fieldName, $filter->getComparisonValue());
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