<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Schema\Table;
use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\NumberFilterInterface;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Domain\DTO\Report\Filters\TextFilterInterface;
use UR\Exception\InvalidArgumentException;

class SqlBuilder implements SqlBuilderInterface
{
    const START_DATE_INDEX = 0;
    const END_DATE_INDEX = 1;

    public function buildSelectQuery(Table $table, array $fields, array $filters)
    {
        $sql = 'SELECT %s FROM %s';

        $tableName = $table->getName();
        $tableColumns = array_keys($table->getColumns());

        foreach($fields as $index=>$field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        if (empty($fields)) {
            throw new InvalidArgumentException('at least one filed must be selected');
        }

        $sql = sprintf($sql, $tableName, implode(',', $fields));

        $conditions = $this->buildFilters($filters);

        if (empty($conditions)) {
            return $sql;
        }

        return $sql . ' WHERE ' . implode(' AND ', $conditions);
    }


    protected function buildFilters(array $filters)
    {
        $sqlConditions = [];

        foreach($filters as $filter) {
            if (!$filter instanceof AbstractFilterInterface) {
                continue;
            }

            $sqlConditions[] = $this->buildSingleFilter($filter);
        }

        return $sqlConditions;
    }

    protected function buildSingleFilter(AbstractFilterInterface $filter)
    {
        if ($filter instanceof DateFilterInterface) {
            $dateRange = $filter->getDateRange();
            if (is_array($dateRange) || count($dateRange) < 2) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN %s AND %s)', $filter->getFieldName(), $dateRange[self::START_DATE_INDEX], $dateRange[self::END_DATE_INDEX]);
        }

        if ($filter instanceof NumberFilterInterface) {
            switch ($filter->getComparisonType()) {
                case NumberFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %d', $filter->getFieldName(), $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_GREATER:
                    return sprintf('%s > %d', $filter->getFieldName(), $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_SMALLER:
                    return sprintf('%s < %d', $filter->getFieldName(), $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                    return sprintf('%s <= %d', $filter->getFieldName(), $filter->getComparisonValue());
                case NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL:
                    return sprintf('%s >= %d', $filter->getFieldName(), $filter->getComparisonValue());
                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        if ($filter instanceof TextFilterInterface) {
            switch ($filter->getComparisonType()) {
                case TextFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $filter->getFieldName(), $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s != %d', $filter->getFieldName(), $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_CONTAINS :
                    return sprintf('CONTAINS(%s, %s)', $filter->getFieldName(), $filter->getComparisonValue());
                case TextFilter::COMPARISON_TYPE_START_WITH:
                    return sprintf('%s LIKE %s%', $filter->getFieldName(), $filter->getComparisonValue());
                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        throw new InvalidArgumentException(sprintf('filter is not supported'));
    }
}